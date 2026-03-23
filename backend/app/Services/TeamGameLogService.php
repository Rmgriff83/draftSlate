<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamGameLogService
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
     * Reuse the same cachedGet pattern as PlayerGameLogService.
     */
    private function cachedGet(string $cacheKey, string $url, array $headers = [], array $query = []): ?array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::info("TeamGameLogService: Redis HIT for {$cacheKey}");
            return $cached;
        }

        try {
            $request = Http::withHeaders($headers)->timeout(10);
            $response = empty($query) ? $request->get($url) : $request->get($url, $query);

            if (!$response->successful()) {
                Log::warning("TeamGameLogService: HTTP {$response->status()} for {$url}", [
                    'query' => $query,
                ]);
                return null;
            }

            $data = $response->json() ?: [];
            Cache::put($cacheKey, $data, $this->cacheTtl);
            Log::info("TeamGameLogService: API response cached for {$cacheKey}");

            return $data;
        } catch (\Exception $e) {
            Log::error("TeamGameLogService: Exception for {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Routing ─────────────────────────────────────────────────────

    /**
     * Main entry point called from PickController.
     */
    public function getTeamStudyData(
        string $homeTeam,
        string $awayTeam,
        string $sport,
        string $pickType,
        string $description
    ): ?array {
        $games = match ($sport) {
            'basketball_nba' => $this->getNbaTeamGameLog($homeTeam, $awayTeam),
            'baseball_mlb' => $this->getMlbTeamGameLog($homeTeam, $awayTeam),
            default => null,
        };

        if ($games === null || empty($games)) {
            return null;
        }

        return $this->buildStudyResponse($games, $pickType, $description, $homeTeam, $awayTeam);
    }

    // ─── NBA ─────────────────────────────────────────────────────────

    /**
     * Fetch recent completed games for both teams from the NBA CDN schedule.
     */
    public function getNbaTeamGameLog(string $homeTeam, string $awayTeam, int $count = 5): ?array
    {
        // Same cache key as PlayerGameLogService — will hit Redis cache
        $cacheKey = 'game_logs.nba.schedule';

        $data = $this->cachedGet(
            $cacheKey,
            'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json',
            $this->nbaHeaders
        );

        if ($data === null) {
            return null;
        }

        // Build fullName → tricode map from schedule data
        $teamMap = $this->buildNbaTeamMap($data);

        $homeTricode = $teamMap[strtolower($homeTeam)] ?? null;
        $awayTricode = $teamMap[strtolower($awayTeam)] ?? null;

        if ($homeTricode === null && $awayTricode === null) {
            return null;
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

                $ht = $game['homeTeam']['teamTricode'] ?? '';
                $at = $game['awayTeam']['teamTricode'] ?? '';

                // Game must involve at least one of the two teams
                if (($homeTricode && ($ht === $homeTricode || $at === $homeTricode))
                    || ($awayTricode && ($ht === $awayTricode || $at === $awayTricode))) {
                    $found[] = [
                        'date' => $parsedDate,
                        'home_team' => $game['homeTeam']['teamName'] ?? '',
                        'away_team' => $game['awayTeam']['teamName'] ?? '',
                        'home_score' => (int) ($game['homeTeam']['score'] ?? 0),
                        'away_score' => (int) ($game['awayTeam']['score'] ?? 0),
                        'home_tricode' => $ht,
                        'away_tricode' => $at,
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * Build a lowercased fullName → tricode map from the NBA schedule data.
     * Tries multiple name fields to maximize matching.
     */
    private function buildNbaTeamMap(array $scheduleData): array
    {
        $map = [];
        $seenTricodes = [];
        $dates = $scheduleData['leagueSchedule']['gameDates'] ?? [];

        foreach ($dates as $dateEntry) {
            foreach ($dateEntry['games'] ?? [] as $game) {
                foreach (['homeTeam', 'awayTeam'] as $side) {
                    $team = $game[$side] ?? [];
                    $tricode = $team['teamTricode'] ?? '';
                    if (empty($tricode) || isset($seenTricodes[$tricode])) {
                        continue;
                    }

                    $seenTricodes[$tricode] = true;
                    $city = $team['teamCity'] ?? '';
                    $name = $team['teamName'] ?? '';
                    $slug = $team['teamSlug'] ?? '';

                    // "Los Angeles Lakers" → "LAL"
                    if ($city && $name) {
                        $map[strtolower("{$city} {$name}")] = $tricode;
                    }
                    // "Lakers" → "LAL"
                    if ($name) {
                        $map[strtolower($name)] = $tricode;
                    }
                    // "los-angeles-lakers" → "LAL"
                    if ($slug) {
                        $map[strtolower($slug)] = $tricode;
                    }
                }
            }
            // NBA has 30 teams; stop once we've seen them all
            if (count($seenTricodes) >= 30) {
                break;
            }
        }

        return $map;
    }

    // ─── MLB ─────────────────────────────────────────────────────────

    /**
     * Fetch recent completed games for both teams from MLB Stats API.
     */
    public function getMlbTeamGameLog(string $homeTeam, string $awayTeam, int $count = 5): ?array
    {
        // Fetch team list (same cache key as PlayerGameLogService)
        $cacheKey = 'game_logs.mlb.team_abbrevs';
        $teamsData = $this->cachedGet($cacheKey, 'https://statsapi.mlb.com/api/v1/teams', [], [
            'sportId' => 1,
        ]);

        if ($teamsData === null || empty($teamsData['teams'])) {
            return null;
        }

        // Build maps: fullName → id, fullName → abbreviation
        $nameToId = [];
        $idToAbbrev = [];
        foreach ($teamsData['teams'] as $team) {
            $fullName = $team['name'] ?? '';
            $id = $team['id'] ?? 0;
            $abbrev = $team['abbreviation'] ?? '';
            if ($fullName && $id) {
                $nameToId[strtolower($fullName)] = $id;
                $idToAbbrev[$id] = $abbrev;
            }
        }

        $homeId = $nameToId[strtolower($homeTeam)] ?? null;
        $awayId = $nameToId[strtolower($awayTeam)] ?? null;

        if ($homeId === null && $awayId === null) {
            return null;
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $allGames = [];

        // Fetch schedule for each team
        foreach ([$homeId, $awayId] as $teamId) {
            if ($teamId === null) {
                continue;
            }

            $schedCacheKey = "game_logs.mlb.team_schedule.{$teamId}.{$startDate}";
            $schedData = $this->cachedGet(
                $schedCacheKey,
                'https://statsapi.mlb.com/api/v1/schedule',
                [],
                [
                    'sportId' => 1,
                    'teamId' => $teamId,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]
            );

            if ($schedData === null) {
                continue;
            }

            foreach ($schedData['dates'] ?? [] as $dateEntry) {
                foreach ($dateEntry['games'] ?? [] as $game) {
                    $status = $game['status']['abstractGameCode'] ?? '';
                    if ($status !== 'F') {
                        continue;
                    }

                    $gamePk = $game['gamePk'] ?? 0;
                    if (isset($allGames[$gamePk])) {
                        continue; // dedupe
                    }

                    $homeData = $game['teams']['home'] ?? [];
                    $awayData = $game['teams']['away'] ?? [];

                    $hId = $homeData['team']['id'] ?? 0;
                    $aId = $awayData['team']['id'] ?? 0;

                    $allGames[$gamePk] = [
                        'date' => $game['officialDate'] ?? ($dateEntry['date'] ?? ''),
                        'home_team' => $homeData['team']['name'] ?? '',
                        'away_team' => $awayData['team']['name'] ?? '',
                        'home_score' => (int) ($homeData['score'] ?? 0),
                        'away_score' => (int) ($awayData['score'] ?? 0),
                        'home_tricode' => $idToAbbrev[$hId] ?? '???',
                        'away_tricode' => $idToAbbrev[$aId] ?? '???',
                    ];
                }
            }
        }

        // Sort by date desc, take last N
        usort($allGames, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($allGames, 0, $count);
    }

    // ─── Study response builder ──────────────────────────────────────

    /**
     * Build the study response array from raw games, matching the shape
     * that the frontend already expects from player prop study data.
     */
    private function buildStudyResponse(
        array $games,
        string $pickType,
        string $description,
        string $homeTeam,
        string $awayTeam
    ): ?array {
        return match ($pickType) {
            'moneyline' => $this->buildMoneylineResponse($games, $description, $homeTeam, $awayTeam),
            'spread' => $this->buildSpreadResponse($games, $description, $homeTeam, $awayTeam),
            'total' => $this->buildTotalResponse($games, $description),
            default => null,
        };
    }

    /**
     * Moneyline: track W/L for the picked team.
     * Description format: "New York Yankees ML (NYY @ BOS)"
     */
    private function buildMoneylineResponse(array $games, string $description, string $homeTeam, string $awayTeam): ?array
    {
        // Extract picked team name from "{TeamName} ML"
        $pickedTeam = null;
        if (preg_match('/^(.+?)\s+ML\b/i', $description, $m)) {
            $pickedTeam = trim($m[1]);
        }

        if ($pickedTeam === null) {
            return null;
        }

        $studyGames = [];
        $hitCount = 0;
        $total = 0;

        foreach ($games as $game) {
            $isHome = $this->teamMatches($pickedTeam, $game['home_team'], $game['home_tricode']);
            $isAway = $this->teamMatches($pickedTeam, $game['away_team'], $game['away_tricode']);

            if (!$isHome && !$isAway) {
                continue;
            }

            $pickedScore = $isHome ? $game['home_score'] : $game['away_score'];
            $oppScore = $isHome ? $game['away_score'] : $game['home_score'];
            $oppTricode = $isHome ? $game['away_tricode'] : $game['home_tricode'];
            $won = $pickedScore > $oppScore;
            $statValue = $won ? 1 : 0;
            $total += $statValue;

            if ($won) {
                $hitCount++;
            }

            $studyGames[] = [
                'date' => date('n/j', strtotime($game['date'])),
                'opponent' => $oppTricode,
                'stat_value' => $statValue,
                'hit' => $won,
                'result' => ($won ? 'W' : 'L') . " {$pickedScore}-{$oppScore}",
            ];
        }

        if (empty($studyGames)) {
            return null;
        }

        $gamesCount = count($studyGames);

        return [
            'stats_available' => true,
            'games' => $studyGames,
            'hit_count' => $hitCount,
            'games_count' => $gamesCount,
            'average' => $gamesCount > 0 ? round($total / $gamesCount, 1) : 0,
            'threshold' => 0.5,
            'side' => $pickedTeam,
            'stat_label' => 'W/L',
        ];
    }

    /**
     * Spread: calculate margin vs spread line for the picked team.
     * Description format: "New York Yankees -1.5 (NYY @ BOS)"
     */
    private function buildSpreadResponse(array $games, string $description, string $homeTeam, string $awayTeam): ?array
    {
        // Extract team name and spread from "{TeamName} {+/-line}"
        $pickedTeam = null;
        $spreadLine = null;
        if (preg_match('/^(.+?)\s+([+-]?\d+\.?\d*)\s*\(/i', $description, $m)) {
            $pickedTeam = trim($m[1]);
            $spreadLine = (float) $m[2];
        }

        if ($pickedTeam === null || $spreadLine === null) {
            return null;
        }

        $studyGames = [];
        $hitCount = 0;
        $total = 0;

        foreach ($games as $game) {
            $isHome = $this->teamMatches($pickedTeam, $game['home_team'], $game['home_tricode']);
            $isAway = $this->teamMatches($pickedTeam, $game['away_team'], $game['away_tricode']);

            if (!$isHome && !$isAway) {
                continue;
            }

            $pickedScore = $isHome ? $game['home_score'] : $game['away_score'];
            $oppScore = $isHome ? $game['away_score'] : $game['home_score'];
            $oppTricode = $isHome ? $game['away_tricode'] : $game['home_tricode'];

            $margin = $pickedScore - $oppScore;
            $total += $margin;

            // Covers if margin + spread > 0 (e.g., team -3.5: needs to win by 4+)
            $covers = ($margin + $spreadLine) > 0;
            if ($covers) {
                $hitCount++;
            }

            $wl = $pickedScore > $oppScore ? 'W' : 'L';

            $studyGames[] = [
                'date' => date('n/j', strtotime($game['date'])),
                'opponent' => $oppTricode,
                'stat_value' => $margin,
                'hit' => $covers,
                'result' => "{$wl} {$pickedScore}-{$oppScore}",
            ];
        }

        if (empty($studyGames)) {
            return null;
        }

        $gamesCount = count($studyGames);

        return [
            'stats_available' => true,
            'games' => $studyGames,
            'hit_count' => $hitCount,
            'games_count' => $gamesCount,
            'average' => $gamesCount > 0 ? round($total / $gamesCount, 1) : 0,
            'threshold' => abs($spreadLine),
            'side' => $pickedTeam,
            'stat_label' => 'Margin',
        ];
    }

    /**
     * Total: calculate combined score vs O/U line.
     * Description format: "Over 210.5 (LAL @ BOS)" or "Under 210.5 (LAL @ BOS)"
     */
    private function buildTotalResponse(array $games, string $description): ?array
    {
        // Extract side and line from "Over/Under {line}"
        $side = null;
        $totalLine = null;
        if (preg_match('/(Over|Under)\s+([\d.]+)/i', $description, $m)) {
            $side = ucfirst(strtolower($m[1]));
            $totalLine = (float) $m[2];
        }

        if ($side === null || $totalLine === null) {
            return null;
        }

        $studyGames = [];
        $hitCount = 0;
        $total = 0;

        foreach ($games as $game) {
            $combinedScore = $game['home_score'] + $game['away_score'];
            $total += $combinedScore;

            $hit = $side === 'Over'
                ? $combinedScore > $totalLine
                : $combinedScore < $totalLine;

            if ($hit) {
                $hitCount++;
            }

            $studyGames[] = [
                'date' => date('n/j', strtotime($game['date'])),
                'opponent' => "{$game['away_tricode']} @ {$game['home_tricode']}",
                'stat_value' => $combinedScore,
                'hit' => $hit,
                'result' => "{$game['away_tricode']} {$game['away_score']}, {$game['home_tricode']} {$game['home_score']}",
            ];
        }

        if (empty($studyGames)) {
            return null;
        }

        $gamesCount = count($studyGames);

        return [
            'stats_available' => true,
            'games' => $studyGames,
            'hit_count' => $hitCount,
            'games_count' => $gamesCount,
            'average' => $gamesCount > 0 ? round($total / $gamesCount, 1) : 0,
            'threshold' => $totalLine,
            'side' => $side,
            'stat_label' => 'Total',
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Flexible team name matching — checks full name containment and tricode.
     */
    private function teamMatches(string $pickedTeam, string $teamFullName, string $teamTricode): bool
    {
        $picked = strtolower(trim($pickedTeam));
        $full = strtolower(trim($teamFullName));

        // Exact match
        if ($picked === $full) {
            return true;
        }

        // Picked is contained in full name or vice versa
        if (str_contains($full, $picked) || str_contains($picked, $full)) {
            return true;
        }

        // Tricode match
        if (strtolower($teamTricode) === $picked) {
            return true;
        }

        return false;
    }
}
