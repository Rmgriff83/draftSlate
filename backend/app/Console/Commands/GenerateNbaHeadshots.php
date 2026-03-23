<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateNbaHeadshots extends Command
{
    protected $signature = 'headshots:nba {--season=}';

    protected $description = 'Generate NBA player headshot JSON map from stats.nba.com';

    public function handle(): int
    {
        $season = $this->option('season') ?: $this->currentSeason();

        $this->info("Fetching NBA players for {$season} season...");

        $categories = ['PTS', 'REB', 'AST', 'STL', 'BLK', 'FG3M'];
        $playerMap = [];

        foreach ($categories as $cat) {
            $this->line("  Querying stat category: {$cat}");

            $response = Http::timeout(30)
                ->withHeaders($this->nbaHeaders())
                ->get('https://stats.nba.com/stats/leagueLeaders', [
                    'LeagueID' => '00',
                    'PerMode' => 'Totals',
                    'Scope' => 'S',
                    'Season' => $season,
                    'SeasonType' => 'Regular Season',
                    'StatCategory' => $cat,
                ]);

            if ($response->failed()) {
                $this->warn("  Failed to fetch {$cat} leaders, skipping...");
                continue;
            }

            $resultSet = $response->json('resultSet', []);
            $headers = $resultSet['headers'] ?? [];
            $rows = $resultSet['rowSet'] ?? [];

            $idIdx = array_search('PLAYER_ID', $headers);
            $nameIdx = array_search('PLAYER', $headers);
            $teamIdx = array_search('TEAM', $headers);

            if ($idIdx === false || $nameIdx === false) {
                $this->warn("  Unexpected response structure for {$cat}, skipping...");
                continue;
            }

            foreach ($rows as $row) {
                $playerId = $row[$idIdx] ?? null;
                $playerName = $row[$nameIdx] ?? null;
                $teamAbbr = $row[$teamIdx] ?? null;

                if (!$playerId || !$playerName) {
                    continue;
                }

                // Deduplicate — first occurrence wins (keeps the team they're most associated with)
                if (!isset($playerMap[$playerName])) {
                    $playerMap[$playerName] = [
                        'url' => "https://cdn.nba.com/headshots/nba/latest/260x190/{$playerId}.png",
                        'team' => $teamAbbr,
                    ];
                }
            }

            // Brief pause between requests to be respectful to the API
            usleep(500_000);
        }

        if (empty($playerMap)) {
            $this->error('No players collected from NBA API.');
            return self::FAILURE;
        }

        $json = json_encode([
            'generated_at' => now()->toIso8601String(),
            'count' => count($playerMap),
            'players' => $playerMap,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk = Storage::disk(config('draftslate.headshots.disk'));
        $disk->put('headshots/nba.json', $json);

        $this->info("Generated NBA headshot map: " . count($playerMap) . " players ({$season} season).");

        return self::SUCCESS;
    }

    private function currentSeason(): string
    {
        $year = (int) date('Y');
        $month = (int) date('n');

        // NBA season spans two calendar years. If before October, we're in the
        // season that started the previous year.
        if ($month < 10) {
            $startYear = $year - 1;
        } else {
            $startYear = $year;
        }

        $endYear = $startYear + 1;
        $endShort = substr((string) $endYear, -2);

        return "{$startYear}-{$endShort}";
    }

    private function nbaHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer' => 'https://www.nba.com/',
            'Accept' => 'application/json',
        ];
    }
}
