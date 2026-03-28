<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OddsApiService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.the-odds-api.com/v4';
    private string $regions;
    private string $oddsFormat;
    private string $bookmaker;
    private int $cacheTtl;

    public function __construct()
    {
        $config = config('draftslate.odds_api');
        $this->apiKey = $config['key'] ?? '';
        $this->regions = $config['regions'];
        $this->oddsFormat = $config['odds_format'];
        $this->bookmaker = $config['bookmaker'];
        $this->cacheTtl = (int) ($config['cache_ttl_seconds'] ?? 180);
    }

    /**
     * Check Redis cache, on miss do Http::get with apiKey auto-injected,
     * cache successful response, return null on failure (never caches errors).
     */
    private function cachedGet(string $cacheKey, string $url, array $query = []): ?array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("OddsApiService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        try {
            $response = Http::timeout(15)->get($url, array_merge([
                'apiKey' => $this->apiKey,
            ], $query));

            if (!$response->successful()) {
                Log::warning("OddsApiService: HTTP {$response->status()} for {$url}");
                return null;
            }

            $data = $response->json() ?: [];
            Cache::put($cacheKey, $data, $this->cacheTtl);
            Log::info("OddsApiService: API response cached for {$cacheKey}");

            return $data;
        } catch (\Exception $e) {
            Log::error("OddsApiService: Exception for {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch game lines + player props for multiple sports, merging all results.
     *
     * Per sport this makes:
     *   1 call  — GET /sports/{sport}/odds (game lines + event discovery)
     *   E calls — GET /sports/{sport}/events/{id}/odds (player props, concurrent)
     *
     * Total: S * (1 + E) calls where S = sports, E = future events per sport.
     */
    public function fetchForSports(array $sports): array
    {
        $allPicks = [];

        foreach ($sports as $sport) {
            [$gameLines, $events] = $this->fetchGameLinesAndEvents($sport);
            $allPicks = array_merge($allPicks, $gameLines);

            $playerProps = $this->fetchPlayerProps($sport, [], $events);
            $allPicks = array_merge($allPicks, $playerProps);
        }

        return $allPicks;
    }

    /**
     * Fetch game lines for a sport AND return the raw events data so
     * fetchPlayerProps can reuse it instead of making a redundant /events call.
     *
     * @return array{0: array, 1: array} [parsedGameLines, rawEventsData]
     */
    public function fetchGameLinesAndEvents(string $sport): array
    {
        $cacheKey = "odds_api.game_lines_response.{$sport}";
        $markets = config('draftslate.odds_api.game_markets');

        $rawEvents = $this->cachedGet($cacheKey, "{$this->baseUrl}/sports/{$sport}/odds", [
            'regions' => $this->regions,
            'oddsFormat' => $this->oddsFormat,
            'markets' => implode(',', $markets),
            'bookmakers' => $this->bookmaker,
        ]);

        if ($rawEvents === null) {
            return [[], []];
        }

        $gameLines = $this->parseGameLinesResponse($rawEvents, $sport);

        return [$gameLines, $rawEvents];
    }

    /**
     * Fetch game lines (convenience wrapper when you don't need raw events).
     */
    public function fetchGameLines(string $sport): array
    {
        [$gameLines] = $this->fetchGameLinesAndEvents($sport);
        return $gameLines;
    }

    /**
     * Fetch player prop markets for a given sport using concurrent HTTP requests.
     *
     * @param string $sport The-odds-api sport key
     * @param array $markets Override prop markets (default: from config)
     * @param array $events Pre-fetched raw events from fetchGameLinesAndEvents()
     */
    public function fetchPlayerProps(string $sport, array $markets = [], array $events = []): array
    {
        if (empty($markets)) {
            $marketsBySport = config('draftslate.odds_api.player_prop_markets_by_sport', []);
            $markets = $marketsBySport[$sport] ?? [];
        }

        if (empty($markets)) {
            return [];
        }

        // Aggregate fast-path: return immediately if already assembled
        $marketsHash = md5(implode(',', $markets));
        $aggCacheKey = "odds_api.player_props.{$sport}.{$marketsHash}";
        $cached = Cache::get($aggCacheKey);
        if ($cached !== null) {
            Log::info("OddsApiService: Redis HIT for {$aggCacheKey}");
            return $cached;
        }

        // Use pre-fetched events or fall back to extracting from game lines response
        if (empty($events)) {
            [, $events] = $this->fetchGameLinesAndEvents($sport);
        }

        if (empty($events)) {
            return [];
        }

        // Pre-filter: skip events with game times in the past or too soon
        $minHours = config('draftslate.odds_api.min_hours_before_game', 1);
        $cutoff = now()->addHours($minHours);
        $futureEvents = array_filter($events, function ($event) use ($cutoff) {
            $gameTime = $event['commence_time'] ?? null;
            if (!$gameTime) {
                return true; // keep events with no game time
            }
            return \Illuminate\Support\Carbon::parse($gameTime)->gt($cutoff);
        });

        if (empty($futureEvents)) {
            Cache::put($aggCacheKey, [], $this->cacheTtl);
            return [];
        }

        $marketsStr = implode(',', $markets);
        $futureEvents = array_values($futureEvents);

        // Split events into cached and uncached
        $cachedResponses = [];
        $uncachedEvents = [];
        foreach ($futureEvents as $event) {
            $eventCacheKey = "odds_api.event_props.{$sport}.{$event['id']}.{$marketsHash}";
            $eventCached = Cache::get($eventCacheKey);
            if ($eventCached !== null) {
                $cachedResponses[$event['id']] = $eventCached;
            } else {
                $uncachedEvents[] = $event;
            }
        }

        // Only fire HTTP for uncached events
        if (!empty($uncachedEvents)) {
            $responses = Http::pool(function (Pool $pool) use ($uncachedEvents, $sport, $marketsStr) {
                foreach ($uncachedEvents as $i => $event) {
                    $pool->as("event_{$i}")
                        ->timeout(15)
                        ->get(
                            "{$this->baseUrl}/sports/{$sport}/events/{$event['id']}/odds",
                            [
                                'apiKey' => $this->apiKey,
                                'regions' => $this->regions,
                                'oddsFormat' => $this->oddsFormat,
                                'markets' => $marketsStr,
                                'bookmakers' => $this->bookmaker,
                            ]
                        );
                }
            });

            foreach ($uncachedEvents as $i => $event) {
                $key = "event_{$i}";
                try {
                    $response = $responses[$key] ?? null;
                    if ($response && $response->successful()) {
                        $propData = $response->json();
                        $eventCacheKey = "odds_api.event_props.{$sport}.{$event['id']}.{$marketsHash}";
                        Cache::put($eventCacheKey, $propData, $this->cacheTtl);
                        $cachedResponses[$event['id']] = $propData;
                    }
                } catch (\Exception $e) {
                    Log::error('OddsApiService: Exception fetching player props', [
                        'sport' => $sport,
                        'event_id' => $event['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Parse all responses (cached + freshly fetched)
        $results = [];
        foreach ($cachedResponses as $propData) {
            if (!empty($propData['bookmakers'])) {
                foreach ($propData['bookmakers'] as $bookmaker) {
                    foreach ($bookmaker['markets'] as $marketData) {
                        $marketKey = $marketData['key'] ?? '';
                        $parsed = $this->parsePlayerPropOutcomes(
                            $propData, $marketData, $marketKey, $sport
                        );
                        if (!empty($parsed)) {
                            $results = array_merge($results, $parsed);
                        }
                    }
                }
            }
        }

        Cache::put($aggCacheKey, $results, $this->cacheTtl);

        return $results;
    }

    /**
     * Fetch current odds for specific event IDs (for odds refresh).
     * Includes all game line + player prop markets for the given sport.
     */
    public function fetchOddsForEvents(array $eventIds, string $sport = 'basketball_nba'): array
    {
        // Build full markets list: game lines + player props for this sport
        $gameMarkets = config('draftslate.odds_api.game_markets', ['h2h', 'spreads', 'totals']);
        $propMarketsBySport = config('draftslate.odds_api.player_prop_markets_by_sport', []);
        $propMarkets = $propMarketsBySport[$sport] ?? [];
        $allMarkets = implode(',', array_merge($gameMarkets, $propMarkets));

        $results = [];

        foreach ($eventIds as $eventId) {
            $data = $this->cachedGet(
                "odds_api.event_odds.{$sport}.{$eventId}",
                "{$this->baseUrl}/sports/{$sport}/events/{$eventId}/odds",
                [
                    'regions' => $this->regions,
                    'oddsFormat' => $this->oddsFormat,
                    'markets' => $allMarkets,
                    'bookmakers' => $this->bookmaker,
                ]
            );

            if ($data !== null) {
                $results[$eventId] = $data;
            }
        }

        return $results;
    }

    /**
     * Fetch current odds for events and return maps of external_id => odds and points.
     *
     * @return array{odds: array<string, int>, points: array<string, float|null>}
     */
    public function fetchCurrentOddsMap(array $eventIds, string $sport): array
    {
        $eventsData = $this->fetchOddsForEvents($eventIds, $sport);
        $oddsMap = [];
        $pointsMap = [];

        foreach ($eventsData as $eventId => $eventData) {
            foreach ($eventData['bookmakers'] ?? [] as $bookmaker) {
                foreach ($bookmaker['markets'] ?? [] as $market) {
                    $marketKey = $market['key'] ?? '';

                    foreach ($market['outcomes'] ?? [] as $outcome) {
                        $odds = $outcome['price'] ?? null;
                        if ($odds === null) {
                            continue;
                        }

                        $name = $outcome['name'] ?? '';
                        $point = $outcome['point'] ?? null;

                        // Game line format: {eventId}_{marketKey}_{name}
                        $gameKey = "{$eventId}_{$marketKey}_{$name}";
                        $oddsMap[$gameKey] = (int) $odds;
                        $pointsMap[$gameKey] = $point;

                        // Player prop format: {eventId}_{marketKey}_{playerName}_{side}
                        if (isset($outcome['description'])) {
                            $playerName = $outcome['description'];
                            $propKey = "{$eventId}_{$marketKey}_{$playerName}_{$name}";
                            $oddsMap[$propKey] = (int) $odds;
                            $pointsMap[$propKey] = $point;
                        }
                    }
                }
            }
        }

        return ['odds' => $oddsMap, 'points' => $pointsMap];
    }

    /**
     * Fetch live game scores for a sport.
     *
     * @return array<string, array>  [eventId => ['home_score' => int, 'away_score' => int, ...]]
     */
    public function fetchScores(string $sport): array
    {
        $rawScores = $this->cachedGet(
            "odds_api.scores_raw.{$sport}",
            "{$this->baseUrl}/sports/{$sport}/scores",
            ['daysFrom' => 1]
        );

        if ($rawScores === null) {
            return [];
        }

        $results = [];
        foreach ($rawScores as $event) {
            $eventId = $event['id'] ?? '';
            if (empty($eventId) || empty($event['scores'])) {
                continue;
            }

            $homeTeam = $event['home_team'] ?? '';
            $awayTeam = $event['away_team'] ?? '';
            $homeScore = null;
            $awayScore = null;

            foreach ($event['scores'] as $s) {
                if ($s['name'] === $homeTeam) {
                    $homeScore = (int) $s['score'];
                } elseif ($s['name'] === $awayTeam) {
                    $awayScore = (int) $s['score'];
                }
            }

            $results[$eventId] = [
                'home_team' => $homeTeam,
                'away_team' => $awayTeam,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'completed' => $event['completed'] ?? false,
                'last_update' => $event['last_update'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Fetch live game scores with a shorter cache TTL (60s) for in-progress games.
     *
     * @return array<string, array>  [eventId => ['home_score' => int, 'away_score' => int, ...]]
     */
    public function fetchScoresLive(string $sport): array
    {
        $cacheKey = "odds_api.scores_live.{$sport}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("OddsApiService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/sports/{$sport}/scores", [
                'apiKey' => $this->apiKey,
                'daysFrom' => 3,
            ]);

            if (!$response->successful()) {
                Log::warning("OddsApiService: HTTP {$response->status()} for scores_live {$sport}");
                return [];
            }

            $rawScores = $response->json() ?: [];
        } catch (\Exception $e) {
            Log::error("OddsApiService: Exception for scores_live {$sport}", ['error' => $e->getMessage()]);
            return [];
        }

        $results = [];
        foreach ($rawScores as $event) {
            $eventId = $event['id'] ?? '';
            if (empty($eventId) || empty($event['scores'])) {
                continue;
            }

            $homeTeam = $event['home_team'] ?? '';
            $awayTeam = $event['away_team'] ?? '';
            $homeScore = null;
            $awayScore = null;

            foreach ($event['scores'] as $s) {
                if ($s['name'] === $homeTeam) {
                    $homeScore = (int) $s['score'];
                } elseif ($s['name'] === $awayTeam) {
                    $awayScore = (int) $s['score'];
                }
            }

            $results[$eventId] = [
                'home_team' => $homeTeam,
                'away_team' => $awayTeam,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'completed' => $event['completed'] ?? false,
                'last_update' => $event['last_update'] ?? null,
            ];
        }

        Cache::put($cacheKey, $results, 60);

        return $results;
    }

    /**
     * Check remaining API quota from response headers.
     * Manual cache — reads header, not JSON body, so can't use cachedGet.
     */
    public function getRemainingQuota(): int
    {
        $cacheKey = 'odds_api.remaining_quota';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("OddsApiService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/sports", [
                'apiKey' => $this->apiKey,
            ]);

            $remaining = (int) $response->header('x-requests-remaining', 0);
            Cache::put($cacheKey, $remaining, $this->cacheTtl);
            Log::info("OddsApiService: API response cached for {$cacheKey}");

            return $remaining;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Fetch historical odds snapshot for a sport at a specific point in time.
     *
     * @return array|null Response with timestamp, previous_timestamp, next_timestamp, and data array
     */
    public function fetchHistoricalOdds(string $sport, string $date): ?array
    {
        $dateHash = md5($date);
        $cacheKey = "odds_api.historical.{$sport}.{$dateHash}";

        return $this->cachedGet($cacheKey, "{$this->baseUrl}/historical/sports/{$sport}/odds", [
            'date' => $date,
            'regions' => $this->regions,
            'oddsFormat' => $this->oddsFormat,
            'bookmakers' => $this->bookmaker,
        ]);
    }

    private function parsePlayerPropOutcomes(array $eventData, array $marketData, string $market, string $sport): array
    {
        $picks = [];

        $homeTeam = $eventData['home_team'] ?? '';
        $awayTeam = $eventData['away_team'] ?? '';
        $gameTime = $eventData['commence_time'] ?? null;
        $eventId = $eventData['id'] ?? '';
        $gameDisplay = "{$awayTeam} @ {$homeTeam}";

        foreach ($marketData['outcomes'] ?? [] as $outcome) {
            $playerName = $outcome['description'] ?? $outcome['name'] ?? null;
            $odds = $outcome['price'] ?? null;
            $point = $outcome['point'] ?? null;

            if ($odds === null) {
                continue;
            }

            $category = $this->marketToCategory($market);
            $side = $outcome['name'] ?? 'Over';
            $description = $playerName
                ? "{$playerName} {$side} {$point} {$category} ({$gameDisplay})"
                : "{$side} {$point} {$category} ({$gameDisplay})";

            $picks[] = [
                'external_id' => "{$eventId}_{$market}_{$playerName}_{$side}",
                'description' => $description,
                'pick_type' => 'player_prop',
                'category' => $category,
                'player_name' => $playerName,
                'home_team' => $homeTeam,
                'away_team' => $awayTeam,
                'game_display' => $gameDisplay,
                'game_time' => $gameTime,
                'sport' => $sport,
                'snapshot_odds' => (int) $odds,
            ];
        }

        return $picks;
    }

    private function parseGameLinesResponse(array $events, string $sport): array
    {
        $picks = [];

        foreach ($events as $event) {
            $homeTeam = $event['home_team'] ?? '';
            $awayTeam = $event['away_team'] ?? '';
            $gameTime = $event['commence_time'] ?? null;
            $eventId = $event['id'] ?? '';
            $gameDisplay = "{$awayTeam} @ {$homeTeam}";

            foreach ($event['bookmakers'] ?? [] as $bookmaker) {
                foreach ($bookmaker['markets'] ?? [] as $market) {
                    $marketKey = $market['key'] ?? '';

                    foreach ($market['outcomes'] ?? [] as $outcome) {
                        $odds = $outcome['price'] ?? null;
                        $point = $outcome['point'] ?? null;
                        $name = $outcome['name'] ?? '';

                        if ($odds === null) {
                            continue;
                        }

                        $pickType = match ($marketKey) {
                            'h2h' => 'moneyline',
                            'spreads' => 'spread',
                            'totals' => 'total',
                            default => $marketKey,
                        };

                        $description = match ($pickType) {
                            'moneyline' => "{$name} ML ({$gameDisplay})",
                            'spread' => "{$name} {$point} ({$gameDisplay})",
                            'total' => "{$name} {$point} ({$gameDisplay})",
                            default => "{$name} ({$gameDisplay})",
                        };

                        $picks[] = [
                            'external_id' => "{$eventId}_{$marketKey}_{$name}",
                            'description' => $description,
                            'pick_type' => $pickType,
                            'category' => $pickType,
                            'player_name' => null,
                            'home_team' => $homeTeam,
                            'away_team' => $awayTeam,
                            'game_display' => $gameDisplay,
                            'game_time' => $gameTime,
                            'sport' => $sport,
                            'snapshot_odds' => (int) $odds,
                        ];
                    }
                }
            }
        }

        return $picks;
    }

    private function marketToCategory(string $market): string
    {
        return match ($market) {
            'player_pass_yds' => 'passing_yards',
            'player_pass_tds' => 'passing_touchdowns',
            'player_rush_yds' => 'rushing_yards',
            'player_receptions' => 'receptions',
            'player_reception_yds' => 'receiving_yards',
            'player_anytime_td' => 'anytime_touchdown',
            'player_points' => 'points',
            'player_rebounds' => 'rebounds',
            'player_assists' => 'assists',
            'player_threes' => 'threes',
            'pitcher_strikeouts' => 'strikeouts',
            'batter_hits' => 'hits',
            'batter_total_bases' => 'total_bases',
            'batter_home_runs' => 'home_runs',
            'player_shots_on_goal' => 'shots_on_goal',
            default => $market,
        };
    }
}
