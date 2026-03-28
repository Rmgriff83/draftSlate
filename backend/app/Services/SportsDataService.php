<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SportsDataService
{
    private array $nbaHeaders = [
        'User-Agent' => 'Mozilla/5.0',
        'Referer' => 'https://www.nba.com',
    ];

    private int $cacheTtl;

    public function __construct()
    {
        $this->cacheTtl = (int) config('draftslate.odds_api.cache_ttl_seconds', 180);
    }

    /**
     * Check Redis cache, on miss do Http::get with custom headers,
     * cache successful response, return null on failure (never caches errors).
     */
    private function cachedGet(string $cacheKey, string $url, array $headers = []): ?array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("SportsDataService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get($url);

            if (!$response->successful()) {
                Log::warning("SportsDataService: HTTP {$response->status()} for {$url}");
                return null;
            }

            $data = $response->json() ?: [];
            Cache::put($cacheKey, $data, $this->cacheTtl);
            Log::info("SportsDataService: API response cached for {$cacheKey}");

            return $data;
        } catch (\Exception $e) {
            Log::error("SportsDataService: Exception for {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch live NBA box scores and return player stats keyed by team matchup.
     *
     * @return array<string, array>  ["{awayTeam} @ {homeTeam}" => ['period' => int, 'clock' => string, 'status' => string, 'players' => [name => stats]]]
     */
    public function fetchNbaBoxScores(): array
    {
        // Top-level assembled cache
        $cacheKey = 'sports_data.nba_box_scores';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("SportsDataService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        $scoreboardData = $this->cachedGet(
            'sports_data.nba_scoreboard',
            'https://cdn.nba.com/static/json/liveData/scoreboard/todaysScoreboard_00.json',
            $this->nbaHeaders
        );

        if ($scoreboardData === null) {
            return [];
        }

        $games = $scoreboardData['scoreboard']['games'] ?? [];
        $results = [];

        foreach ($games as $game) {
            $gameId = $game['gameId'] ?? null;
            $status = $game['gameStatus'] ?? 1; // 1=not started, 2=in progress, 3=final
            if (!$gameId || $status < 2) {
                continue; // Skip games that haven't started
            }

            $boxScore = $this->fetchNbaBoxScore($gameId);
            if (empty($boxScore)) {
                continue;
            }

            $gameData = $boxScore['game'] ?? [];
            $homeTeamName = $gameData['homeTeam']['teamName'] ?? '';
            $awayTeamName = $gameData['awayTeam']['teamName'] ?? '';
            $homeCity = $gameData['homeTeam']['teamCity'] ?? '';
            $awayCity = $gameData['awayTeam']['teamCity'] ?? '';
            $homeFull = trim("{$homeCity} {$homeTeamName}");
            $awayFull = trim("{$awayCity} {$awayTeamName}");

            $players = [];

            foreach (['homeTeam', 'awayTeam'] as $side) {
                foreach ($gameData[$side]['players'] ?? [] as $player) {
                    $name = $player['name'] ?? '';
                    $stats = $player['statistics'] ?? [];
                    if (empty($name) || empty($stats)) {
                        continue;
                    }

                    $players[$name] = [
                        'points' => (int) ($stats['points'] ?? 0),
                        'rebounds' => (int) ($stats['reboundsTotal'] ?? 0),
                        'assists' => (int) ($stats['assists'] ?? 0),
                        'threes' => (int) ($stats['threePointersMade'] ?? 0),
                        'minutes' => $stats['minutesCalculated'] ?? null,
                        'team' => $side === 'homeTeam' ? $homeFull : $awayFull,
                        'played' => $player['played'] ?? '1',
                        'not_playing_reason' => $player['notPlayingReason'] ?? null,
                    ];
                }
            }

            // Key by multiple formats for flexible matching
            $matchupKey = "{$awayFull} @ {$homeFull}";
            $gameInfo = [
                'home_team' => $homeFull,
                'away_team' => $awayFull,
                'period' => (int) ($gameData['period'] ?? 0),
                'clock' => $gameData['gameClock'] ?? '',
                'status' => $gameData['gameStatusText'] ?? '',
                'players' => $players,
            ];

            $results[$matchupKey] = $gameInfo;
            // Also key by home team name alone for fuzzy matching
            $results["home:{$homeFull}"] = $gameInfo;
            $results["home:{$homeTeamName}"] = $gameInfo;
            $results["away:{$awayFull}"] = $gameInfo;
            $results["away:{$awayTeamName}"] = $gameInfo;
        }

        Cache::put($cacheKey, $results, $this->cacheTtl);

        return $results;
    }

    /**
     * Fetch live NBA box scores with a shorter cache TTL (60s) for in-progress games.
     */
    public function fetchNbaBoxScoresLive(): array
    {
        $cacheKey = 'sports_data.nba_box_scores_live';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("SportsDataService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        $scoreboardData = $this->cachedGet(
            'sports_data.nba_scoreboard',
            'https://cdn.nba.com/static/json/liveData/scoreboard/todaysScoreboard_00.json',
            $this->nbaHeaders
        );

        if ($scoreboardData === null) {
            return [];
        }

        $games = $scoreboardData['scoreboard']['games'] ?? [];
        $results = [];

        foreach ($games as $game) {
            $gameId = $game['gameId'] ?? null;
            $status = $game['gameStatus'] ?? 1;
            if (!$gameId || $status < 2) {
                continue;
            }

            $boxScore = $this->fetchNbaBoxScore($gameId);
            if (empty($boxScore)) {
                continue;
            }

            $gameData = $boxScore['game'] ?? [];
            $homeTeamName = $gameData['homeTeam']['teamName'] ?? '';
            $awayTeamName = $gameData['awayTeam']['teamName'] ?? '';
            $homeCity = $gameData['homeTeam']['teamCity'] ?? '';
            $awayCity = $gameData['awayTeam']['teamCity'] ?? '';
            $homeFull = trim("{$homeCity} {$homeTeamName}");
            $awayFull = trim("{$awayCity} {$awayTeamName}");

            $players = [];

            foreach (['homeTeam', 'awayTeam'] as $side) {
                foreach ($gameData[$side]['players'] ?? [] as $player) {
                    $name = $player['name'] ?? '';
                    $stats = $player['statistics'] ?? [];
                    if (empty($name) || empty($stats)) {
                        continue;
                    }

                    $players[$name] = [
                        'points' => (int) ($stats['points'] ?? 0),
                        'rebounds' => (int) ($stats['reboundsTotal'] ?? 0),
                        'assists' => (int) ($stats['assists'] ?? 0),
                        'threes' => (int) ($stats['threePointersMade'] ?? 0),
                        'minutes' => $stats['minutesCalculated'] ?? null,
                        'team' => $side === 'homeTeam' ? $homeFull : $awayFull,
                        'played' => $player['played'] ?? '1',
                        'not_playing_reason' => $player['notPlayingReason'] ?? null,
                    ];
                }
            }

            $matchupKey = "{$awayFull} @ {$homeFull}";
            $gameInfo = [
                'home_team' => $homeFull,
                'away_team' => $awayFull,
                'period' => (int) ($gameData['period'] ?? 0),
                'clock' => $gameData['gameClock'] ?? '',
                'status' => $gameData['gameStatusText'] ?? '',
                'players' => $players,
            ];

            $results[$matchupKey] = $gameInfo;
            $results["home:{$homeFull}"] = $gameInfo;
            $results["home:{$homeTeamName}"] = $gameInfo;
            $results["away:{$awayFull}"] = $gameInfo;
            $results["away:{$awayTeamName}"] = $gameInfo;
        }

        Cache::put($cacheKey, $results, 60);

        return $results;
    }

    /**
     * Fetch NBA box scores for a specific past date using the league schedule.
     * Returns the same format as fetchNbaBoxScoresLive().
     */
    public function fetchNbaBoxScoresForDate(string $date): array
    {
        $cacheKey = "sports_data.nba_box_scores_date.{$date}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("SportsDataService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        $schedule = $this->cachedGet(
            'sports_data.nba_schedule',
            'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json',
            $this->nbaHeaders
        );

        if ($schedule === null) {
            return [];
        }

        $gameDates = $schedule['leagueSchedule']['gameDates'] ?? [];
        $gameIds = [];

        foreach ($gameDates as $dateEntry) {
            $parsedDate = date('Y-m-d', strtotime($dateEntry['gameDate'] ?? ''));
            if ($parsedDate !== $date) {
                continue;
            }

            foreach ($dateEntry['games'] ?? [] as $game) {
                $status = $game['gameStatus'] ?? 1;
                if ($status >= 2 && !empty($game['gameId'])) {
                    $gameIds[] = $game['gameId'];
                }
            }
            break;
        }

        if (empty($gameIds)) {
            Cache::put($cacheKey, [], $this->cacheTtl);
            return [];
        }

        $results = [];
        foreach ($gameIds as $gameId) {
            $boxScore = $this->fetchNbaBoxScore($gameId);
            if (empty($boxScore)) {
                continue;
            }

            $gameData = $boxScore['game'] ?? [];
            $homeTeamName = $gameData['homeTeam']['teamName'] ?? '';
            $awayTeamName = $gameData['awayTeam']['teamName'] ?? '';
            $homeCity = $gameData['homeTeam']['teamCity'] ?? '';
            $awayCity = $gameData['awayTeam']['teamCity'] ?? '';
            $homeFull = trim("{$homeCity} {$homeTeamName}");
            $awayFull = trim("{$awayCity} {$awayTeamName}");

            $players = [];

            foreach (['homeTeam', 'awayTeam'] as $side) {
                foreach ($gameData[$side]['players'] ?? [] as $player) {
                    $name = $player['name'] ?? '';
                    $stats = $player['statistics'] ?? [];
                    if (empty($name) || empty($stats)) {
                        continue;
                    }

                    $players[$name] = [
                        'points' => (int) ($stats['points'] ?? 0),
                        'rebounds' => (int) ($stats['reboundsTotal'] ?? 0),
                        'assists' => (int) ($stats['assists'] ?? 0),
                        'threes' => (int) ($stats['threePointersMade'] ?? 0),
                        'minutes' => $stats['minutesCalculated'] ?? null,
                        'team' => $side === 'homeTeam' ? $homeFull : $awayFull,
                        'played' => $player['played'] ?? '1',
                        'not_playing_reason' => $player['notPlayingReason'] ?? null,
                    ];
                }
            }

            $matchupKey = "{$awayFull} @ {$homeFull}";
            $gameInfo = [
                'home_team' => $homeFull,
                'away_team' => $awayFull,
                'period' => (int) ($gameData['period'] ?? 0),
                'clock' => $gameData['gameClock'] ?? '',
                'status' => $gameData['gameStatusText'] ?? '',
                'players' => $players,
            ];

            $results[$matchupKey] = $gameInfo;
            $results["home:{$homeFull}"] = $gameInfo;
            $results["home:{$homeTeamName}"] = $gameInfo;
            $results["away:{$awayFull}"] = $gameInfo;
            $results["away:{$awayTeamName}"] = $gameInfo;
        }

        Cache::put($cacheKey, $results, $this->cacheTtl);

        return $results;
    }

    private function fetchNbaBoxScore(string $gameId): array
    {
        $data = $this->cachedGet(
            "sports_data.nba_boxscore.{$gameId}",
            "https://cdn.nba.com/static/json/liveData/boxscore/boxscore_{$gameId}.json",
            $this->nbaHeaders
        );

        return $data ?? [];
    }

    /**
     * Look up a player's current stats from box score data.
     *
     * @param array $boxScores Result from fetchNbaBoxScores()
     * @param string $playerName e.g. "Donovan Clingan"
     * @param string $homeTeam e.g. "Denver Nuggets"
     * @param string $awayTeam e.g. "Portland Trail Blazers"
     */
    public function findPlayerStats(array $boxScores, string $playerName, string $homeTeam, string $awayTeam): ?array
    {
        // Try matching by team names
        $lookupKeys = [
            "{$awayTeam} @ {$homeTeam}",
            "home:{$homeTeam}",
            "away:{$awayTeam}",
        ];

        foreach ($lookupKeys as $key) {
            if (!isset($boxScores[$key])) {
                continue;
            }

            $gameData = $boxScores[$key];
            $players = $gameData['players'] ?? [];

            // Exact match
            if (isset($players[$playerName])) {
                return array_merge($players[$playerName], [
                    'period' => $gameData['period'] ?? null,
                    'clock' => $gameData['clock'] ?? null,
                    'status' => $gameData['status'] ?? null,
                ]);
            }

            // Normalized match (strip diacritics + suffixes)
            $normalizedSearch = $this->normalizeName($playerName);
            foreach ($players as $name => $stats) {
                if ($this->normalizeName($name) === $normalizedSearch) {
                    return array_merge($stats, [
                        'period' => $gameData['period'] ?? null,
                        'clock' => $gameData['clock'] ?? null,
                        'status' => $gameData['status'] ?? null,
                    ]);
                }
            }

            // Fuzzy match (last name, normalized)
            $lastName = $this->extractLastName($playerName);
            foreach ($players as $name => $stats) {
                if ($this->extractLastName($name) === $lastName) {
                    return array_merge($stats, [
                        'period' => $gameData['period'] ?? null,
                        'clock' => $gameData['clock'] ?? null,
                        'status' => $gameData['status'] ?? null,
                    ]);
                }
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        // Strip suffixes like Jr., Sr., II, III, IV
        $name = preg_replace('/\s+(Jr\.?|Sr\.?|I{1,3}|IV|V)$/i', '', trim($name));

        // Transliterate diacritics to ASCII
        if (function_exists('transliterator_transliterate')) {
            $name = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
        } else {
            $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        }

        return strtolower(trim($name));
    }

    private function extractLastName(string $name): string
    {
        $normalized = $this->normalizeName($name);
        $parts = explode(' ', $normalized);

        // Handle hyphenated or multi-word last names by using last part
        return end($parts);
    }

    /**
     * Get the stat key that corresponds to a pick's category.
     */
    public function categoryToStatKey(string $category): ?string
    {
        return match ($category) {
            'points', 'passing_yards', 'player_points' => 'points',
            'rebounds', 'player_rebounds' => 'rebounds',
            'assists', 'player_assists' => 'assists',
            'threes', 'player_threes' => 'threes',
            default => null,
        };
    }
}
