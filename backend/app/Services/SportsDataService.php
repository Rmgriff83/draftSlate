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

    /**
     * Fetch live NBA box scores and return player stats keyed by team matchup.
     *
     * @return array<string, array>  ["{awayTeam} @ {homeTeam}" => ['period' => int, 'clock' => string, 'status' => string, 'players' => [name => stats]]]
     */
    public function fetchNbaBoxScores(): array
    {
        $cacheKey = 'sports_data.nba_box_scores';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Step 1: Get today's scoreboard for game IDs
            $scoreboard = Http::withHeaders($this->nbaHeaders)
                ->timeout(10)
                ->get('https://cdn.nba.com/static/json/liveData/scoreboard/todaysScoreboard_00.json');

            if (!$scoreboard->successful()) {
                return [];
            }

            $games = $scoreboard->json()['scoreboard']['games'] ?? [];
            $results = [];

            // Step 2: Fetch box score for each active/completed game
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

            // Cache for 2 minutes
            Cache::put($cacheKey, $results, now()->addMinutes(2));

            return $results;
        } catch (\Exception $e) {
            Log::error('SportsDataService: Error fetching NBA box scores', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function fetchNbaBoxScore(string $gameId): array
    {
        try {
            $response = Http::withHeaders($this->nbaHeaders)
                ->timeout(10)
                ->get("https://cdn.nba.com/static/json/liveData/boxscore/boxscore_{$gameId}.json");

            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            Log::debug("SportsDataService: Failed to fetch box score for {$gameId}");
            return [];
        }
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
