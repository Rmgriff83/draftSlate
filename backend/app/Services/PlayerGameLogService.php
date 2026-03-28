<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlayerGameLogService
{
    private int $cacheTtl;

    private array $nbaHeaders = [
        'User-Agent' => 'Mozilla/5.0',
        'Referer' => 'https://www.nba.com',
    ];

    public function __construct()
    {
        $this->cacheTtl = (int) config('draftslate.odds_api.cache_ttl_seconds', 180);
    }

    /**
     * Check Redis cache, on miss do Http::get,
     * cache successful response, return null on failure (never caches errors).
     */
    private function cachedGet(string $cacheKey, string $url, array $headers = [], array $query = []): ?array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("PlayerGameLogService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        try {
            $request = Http::withHeaders($headers)->timeout(10);
            $response = empty($query) ? $request->get($url) : $request->get($url, $query);

            if (!$response->successful()) {
                Log::warning("PlayerGameLogService: HTTP {$response->status()} for {$url}", [
                    'query' => $query,
                ]);
                return null;
            }

            $data = $response->json() ?: [];
            Cache::put($cacheKey, $data, $this->cacheTtl);
            Log::info("PlayerGameLogService: API response cached for {$cacheKey}");

            return $data;
        } catch (\Exception $e) {
            Log::error("PlayerGameLogService: Exception for {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Routing ─────────────────────────────────────────────────────

    /**
     * Orchestrator: routes to the correct sport-specific handler.
     * Returns null if sport is unsupported or data is unavailable.
     */
    public function getPlayerStudyData(string $playerName, string $sport, string $category, float $threshold, string $side = 'Over'): ?array
    {
        return match ($sport) {
            'basketball_nba' => $this->getNbaStudyData($playerName, $category, $threshold, $side),
            'baseball_mlb' => $this->getMlbStudyData($playerName, $category, $threshold, $side),
            default => null,
        };
    }

    /**
     * Map our internal category to a stat field key (used across sports).
     */
    public function categoryToStatField(string $category): ?string
    {
        return match ($category) {
            // NBA
            'points', 'player_points' => 'pts',
            'rebounds', 'player_rebounds' => 'reb',
            'assists', 'player_assists' => 'ast',
            'threes', 'player_threes' => 'fg3m',
            // MLB
            'pitcher_strikeouts' => 'strikeOuts',
            'batter_hits' => 'hits',
            'batter_total_bases' => 'totalBases',
            'batter_home_runs' => 'homeRuns',
            default => null,
        };
    }

    /**
     * Human-readable label for a category.
     */
    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'points', 'player_points' => 'PTS',
            'rebounds', 'player_rebounds' => 'REB',
            'assists', 'player_assists' => 'AST',
            'threes', 'player_threes' => '3PM',
            'pitcher_strikeouts' => 'K',
            'batter_hits' => 'H',
            'batter_total_bases' => 'TB',
            'batter_home_runs' => 'HR',
            default => strtoupper($category),
        };
    }

    // ─── NBA (NBA CDN) ────────────────────────────────────────────────

    private function getNbaStudyData(string $playerName, string $category, float $threshold, string $side): ?array
    {
        $statField = $this->categoryToStatField($category);
        if ($statField === null) {
            return null;
        }

        $teamInfo = $this->findNbaPlayerTeam($playerName);
        if ($teamInfo === null || empty($teamInfo['team_abbreviation'])) {
            return null;
        }

        $recentGames = $this->getNbaRecentTeamGames($teamInfo['team_abbreviation'], 5);
        if (empty($recentGames)) {
            return null;
        }

        $matchedName = $teamInfo['player_name_matched'];
        $games = [];
        $hitCount = 0;
        $total = 0;

        foreach ($recentGames as $gameInfo) {
            $playerStats = $this->getNbaPlayerStatsFromBoxScore($gameInfo['gameId'], $matchedName);

            if ($playerStats === null && $matchedName !== $playerName) {
                $playerStats = $this->getNbaPlayerStatsFromBoxScore($gameInfo['gameId'], $playerName);
            }

            if ($playerStats === null) {
                continue;
            }

            $statValue = (float) ($playerStats[$statField] ?? 0);
            $total += $statValue;

            $hit = $side === 'Over' ? $statValue > $threshold : $statValue < $threshold;
            if ($hit) {
                $hitCount++;
            }

            $games[] = [
                'date' => date('n/j', strtotime($gameInfo['date'])),
                'opponent' => $playerStats['opponent'] ?? '???',
                'stat_value' => $statValue,
                'hit' => $hit,
                'pts' => $playerStats['pts'],
                'reb' => $playerStats['reb'],
                'ast' => $playerStats['ast'],
                'fg3m' => $playerStats['fg3m'],
                'min' => $playerStats['min'],
                'result' => $playerStats['result'],
            ];
        }

        if (empty($games)) {
            return null;
        }

        $gamesCount = count($games);

        return [
            'stats_available' => true,
            'games' => $games,
            'hit_count' => $hitCount,
            'games_count' => $gamesCount,
            'average' => $gamesCount > 0 ? round($total / $gamesCount, 1) : 0,
        ];
    }

    /**
     * Find an NBA player's team by scanning recent box scores from the NBA CDN.
     */
    public function findNbaPlayerTeam(string $playerName): ?array
    {
        $cacheKey = 'game_logs.nba.player_team.' . md5(strtolower(trim($playerName)));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $scheduleKey = 'game_logs.nba.schedule';
        $data = $this->cachedGet(
            $scheduleKey,
            'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json',
            $this->nbaHeaders
        );

        if ($data === null) {
            return null;
        }

        $dates = $data['leagueSchedule']['gameDates'] ?? [];
        $today = date('Y-m-d');
        $normalizedSearch = $this->normalizeName($playerName);
        $lastNameSearch = $this->extractLastName($playerName);
        $gamesChecked = 0;
        $maxGamesToCheck = 30;

        for ($i = count($dates) - 1; $i >= 0 && $gamesChecked < $maxGamesToCheck; $i--) {
            $dateEntry = $dates[$i];
            $dateStr = $dateEntry['gameDate'] ?? '';
            $parsedDate = date('Y-m-d', strtotime($dateStr));

            if ($parsedDate > $today) {
                continue;
            }

            foreach ($dateEntry['games'] ?? [] as $game) {
                if ($gamesChecked >= $maxGamesToCheck) {
                    break;
                }

                if (($game['gameStatus'] ?? 0) !== 3) {
                    continue;
                }

                $gameId = $game['gameId'] ?? '';
                if (empty($gameId)) {
                    continue;
                }

                $boxCacheKey = "game_logs.nba.boxscore.{$gameId}";
                $boxData = $this->cachedGet(
                    $boxCacheKey,
                    "https://cdn.nba.com/static/json/liveData/boxscore/boxscore_{$gameId}.json",
                    $this->nbaHeaders
                );

                $gamesChecked++;

                if ($boxData === null) {
                    continue;
                }

                $gameData = $boxData['game'] ?? [];

                foreach (['homeTeam', 'awayTeam'] as $side) {
                    $teamData = $gameData[$side] ?? [];
                    foreach ($teamData['players'] ?? [] as $player) {
                        $name = $player['name'] ?? '';
                        if (empty($name)) {
                            continue;
                        }

                        $normalizedName = $this->normalizeName($name);
                        if ($normalizedName === $normalizedSearch
                            || $this->extractLastName($name) === $lastNameSearch) {
                            $result = [
                                'team_abbreviation' => $teamData['teamTricode'] ?? '',
                                'player_name_matched' => $name,
                            ];

                            Cache::put($cacheKey, $result, $this->cacheTtl);

                            return $result;
                        }
                    }
                }
            }
        }

        return null;
    }

    public function getNbaRecentTeamGames(string $teamTricode, int $count = 5): array
    {
        $cacheKey = 'game_logs.nba.schedule';

        $data = $this->cachedGet(
            $cacheKey,
            'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json',
            $this->nbaHeaders
        );

        if ($data === null) {
            return [];
        }

        $dates = $data['leagueSchedule']['gameDates'] ?? [];
        $today = date('Y-m-d');
        $found = [];

        for ($i = count($dates) - 1; $i >= 0 && count($found) < $count; $i--) {
            $dateEntry = $dates[$i];
            $dateStr = $dateEntry['gameDate'] ?? '';
            $parsedDate = date('Y-m-d', strtotime($dateStr));

            if ($parsedDate > $today) {
                continue;
            }

            foreach ($dateEntry['games'] ?? [] as $game) {
                if (count($found) >= $count) {
                    break;
                }

                if (($game['gameStatus'] ?? 0) !== 3) {
                    continue;
                }

                $homeTricode = $game['homeTeam']['teamTricode'] ?? '';
                $awayTricode = $game['awayTeam']['teamTricode'] ?? '';

                if ($homeTricode !== $teamTricode && $awayTricode !== $teamTricode) {
                    continue;
                }

                $found[] = [
                    'gameId' => $game['gameId'] ?? '',
                    'date' => $parsedDate,
                    'homeTricode' => $homeTricode,
                    'awayTricode' => $awayTricode,
                ];
            }
        }

        return $found;
    }

    public function getNbaPlayerStatsFromBoxScore(string $gameId, string $playerName): ?array
    {
        $cacheKey = "game_logs.nba.boxscore.{$gameId}";

        $data = $this->cachedGet(
            $cacheKey,
            "https://cdn.nba.com/static/json/liveData/boxscore/boxscore_{$gameId}.json",
            $this->nbaHeaders
        );

        if ($data === null) {
            return null;
        }

        $gameData = $data['game'] ?? [];
        $normalizedSearch = $this->normalizeName($playerName);
        $lastNameSearch = $this->extractLastName($playerName);

        foreach (['homeTeam', 'awayTeam'] as $side) {
            $teamData = $gameData[$side] ?? [];
            $teamScore = $teamData['score'] ?? 0;
            $oppSide = $side === 'homeTeam' ? 'awayTeam' : 'homeTeam';
            $oppTricode = $gameData[$oppSide]['teamTricode'] ?? '';
            $oppScore = $gameData[$oppSide]['score'] ?? 0;

            foreach ($teamData['players'] ?? [] as $player) {
                $name = $player['name'] ?? '';
                if (empty($name)) {
                    continue;
                }

                if ($this->normalizeName($name) !== $normalizedSearch
                    && $this->extractLastName($name) !== $lastNameSearch) {
                    continue;
                }

                $stats = $player['statistics'] ?? [];
                $wl = $teamScore > $oppScore ? 'W' : 'L';

                return [
                    'pts' => (int) ($stats['points'] ?? 0),
                    'reb' => (int) ($stats['reboundsTotal'] ?? 0),
                    'ast' => (int) ($stats['assists'] ?? 0),
                    'fg3m' => (int) ($stats['threePointersMade'] ?? 0),
                    'min' => $stats['minutesCalculated'] ?? '--',
                    'opponent' => $oppTricode,
                    'result' => "{$wl} {$teamScore}-{$oppScore}",
                ];
            }
        }

        return null;
    }

    // ─── MLB (statsapi.mlb.com — official, no auth) ──────────────────

    private function getMlbStudyData(string $playerName, string $category, float $threshold, string $side): ?array
    {
        $statField = $this->categoryToStatField($category);
        if ($statField === null) {
            return null;
        }

        $isPitching = str_starts_with($category, 'pitcher_');
        $group = $isPitching ? 'pitching' : 'hitting';

        // Step 1: Find the MLB player ID
        $playerId = $this->findMlbPlayerId($playerName);
        if ($playerId === null) {
            return null;
        }

        // Step 2: Fetch game log — try current year, fall back to previous year
        $season = (int) date('Y');
        $logs = $this->getMlbGameLog($playerId, $season, $group);
        if (empty($logs)) {
            $logs = $this->getMlbGameLog($playerId, $season - 1, $group);
        }
        if (empty($logs)) {
            return null;
        }

        // Step 3: Get team abbreviation map for opponent display
        $teamAbbrevs = $this->getMlbTeamAbbreviations();

        // Take last 5 games (API returns chronological, last entries are most recent)
        $recentLogs = array_slice($logs, -5);
        // Reverse so most recent is first
        $recentLogs = array_reverse($recentLogs);

        $games = [];
        $hitCount = 0;
        $total = 0;

        foreach ($recentLogs as $split) {
            $stat = $split['stat'] ?? [];
            $statValue = (float) ($stat[$statField] ?? 0);
            $total += $statValue;

            $hit = $side === 'Over' ? $statValue > $threshold : $statValue < $threshold;
            if ($hit) {
                $hitCount++;
            }

            $date = $split['date'] ?? '';
            $dateFormatted = $date ? date('n/j', strtotime($date)) : '?/?';

            $oppId = $split['opponent']['id'] ?? 0;
            $opponent = $teamAbbrevs[$oppId] ?? '???';

            $isWin = $split['isWin'] ?? false;
            $wl = $isWin ? 'W' : 'L';

            if ($isPitching) {
                $games[] = [
                    'date' => $dateFormatted,
                    'opponent' => $opponent,
                    'stat_value' => $statValue,
                    'hit' => $hit,
                    'strikeOuts' => (int) ($stat['strikeOuts'] ?? 0),
                    'inningsPitched' => $stat['inningsPitched'] ?? '--',
                    'hits' => (int) ($stat['hits'] ?? 0),
                    'earnedRuns' => (int) ($stat['earnedRuns'] ?? 0),
                    'baseOnBalls' => (int) ($stat['baseOnBalls'] ?? 0),
                    'era' => $stat['era'] ?? '--',
                    'result' => $wl,
                ];
            } else {
                $games[] = [
                    'date' => $dateFormatted,
                    'opponent' => $opponent,
                    'stat_value' => $statValue,
                    'hit' => $hit,
                    'hits' => (int) ($stat['hits'] ?? 0),
                    'homeRuns' => (int) ($stat['homeRuns'] ?? 0),
                    'rbi' => (int) ($stat['rbi'] ?? 0),
                    'strikeOuts' => (int) ($stat['strikeOuts'] ?? 0),
                    'baseOnBalls' => (int) ($stat['baseOnBalls'] ?? 0),
                    'totalBases' => (int) ($stat['totalBases'] ?? 0),
                    'result' => $wl,
                ];
            }
        }

        if (empty($games)) {
            return null;
        }

        $gamesCount = count($games);

        return [
            'stats_available' => true,
            'sport' => 'baseball_mlb',
            'stat_group' => $group,
            'games' => $games,
            'hit_count' => $hitCount,
            'games_count' => $gamesCount,
            'average' => $gamesCount > 0 ? round($total / $gamesCount, 1) : 0,
        ];
    }

    /**
     * Search MLB Stats API for a player by name and return their ID.
     */
    public function findMlbPlayerId(string $playerName): ?int
    {
        $cacheKey = 'game_logs.mlb.player_id.' . md5(strtolower(trim($playerName)));

        $data = $this->cachedGet($cacheKey, 'https://statsapi.mlb.com/api/v1/people/search', [], [
            'names' => $playerName,
        ]);

        if ($data === null || empty($data['people'])) {
            return null;
        }

        $normalizedSearch = strtolower(trim($playerName));

        // Exact match first
        foreach ($data['people'] as $person) {
            if (strtolower(trim($person['fullName'] ?? '')) === $normalizedSearch) {
                return $person['id'];
            }
        }

        // Last name match
        $parts = explode(' ', $normalizedSearch);
        $lastName = end($parts);
        foreach ($data['people'] as $person) {
            if (strtolower(trim($person['lastName'] ?? '')) === $lastName) {
                return $person['id'];
            }
        }

        // Fall back to first active result
        foreach ($data['people'] as $person) {
            if ($person['active'] ?? false) {
                return $person['id'];
            }
        }

        return $data['people'][0]['id'] ?? null;
    }

    /**
     * Fetch a player's game log from the MLB Stats API.
     *
     * @return array[] Array of split objects with stat/opponent/date, or empty.
     */
    public function getMlbGameLog(int $playerId, int $season, string $group = 'hitting'): array
    {
        $cacheKey = "game_logs.mlb.gamelog.{$playerId}.{$season}.{$group}";

        $data = $this->cachedGet($cacheKey, "https://statsapi.mlb.com/api/v1/people/{$playerId}/stats", [], [
            'stats' => 'gameLog',
            'season' => $season,
            'group' => $group,
        ]);

        if ($data === null) {
            return [];
        }

        return $data['stats'][0]['splits'] ?? [];
    }

    /**
     * Fetch MLB team abbreviations map (team_id => abbreviation).
     * Cached via cachedGet with same TTL.
     */
    public function getMlbTeamAbbreviations(): array
    {
        $cacheKey = 'game_logs.mlb.team_abbrevs';

        $data = $this->cachedGet($cacheKey, 'https://statsapi.mlb.com/api/v1/teams', [], [
            'sportId' => 1,
        ]);

        if ($data === null || empty($data['teams'])) {
            return [];
        }

        $map = [];
        foreach ($data['teams'] as $team) {
            $map[$team['id']] = $team['abbreviation'] ?? $team['teamName'] ?? '???';
        }

        return $map;
    }

    // ─── Shared helpers ──────────────────────────────────────────────

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+(Jr\.?|Sr\.?|I{1,3}|IV|V)$/i', '', trim($name));

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

        return end($parts);
    }
}
