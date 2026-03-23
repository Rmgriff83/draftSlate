<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateMlbHeadshots extends Command
{
    protected $signature = 'headshots:mlb {--season=}';

    protected $description = 'Generate MLB player headshot JSON map from statsapi.mlb.com';

    public function handle(): int
    {
        $season = $this->option('season') ?: (int) date('Y');

        $this->info("Fetching MLB players for {$season} season...");

        $response = Http::timeout(30)->get("https://statsapi.mlb.com/api/v1/sports/1/players", [
            'season' => $season,
        ]);

        if ($response->failed()) {
            // Fall back to previous year if current year fails (pre-season)
            $fallback = $season - 1;
            $this->warn("Failed for {$season}, trying {$fallback}...");

            $response = Http::timeout(30)->get("https://statsapi.mlb.com/api/v1/sports/1/players", [
                'season' => $fallback,
            ]);

            if ($response->failed()) {
                $this->error("Failed to fetch MLB players for both {$season} and {$fallback}.");
                return self::FAILURE;
            }

            $season = $fallback;
        }

        $people = $response->json('people', []);

        if (empty($people)) {
            $this->error('No players returned from MLB API.');
            return self::FAILURE;
        }

        $players = [];

        foreach ($people as $player) {
            $id = $player['id'] ?? null;
            $name = $player['fullName'] ?? null;
            $team = $player['currentTeam']['abbreviation'] ?? null;

            if (!$id || !$name) {
                continue;
            }

            $players[$name] = [
                'url' => "https://img.mlbstatic.com/mlb-photos/image/upload/d_people:generic:headshot:67:current.png/w_213,q_auto:best/v1/people/{$id}/headshot/67/current",
                'team' => $team,
            ];
        }

        $json = json_encode([
            'generated_at' => now()->toIso8601String(),
            'count' => count($players),
            'players' => $players,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk = Storage::disk(config('draftslate.headshots.disk'));
        $disk->put('headshots/mlb.json', $json);

        $this->info("Generated MLB headshot map: " . count($players) . " players ({$season} season).");

        return self::SUCCESS;
    }
}
