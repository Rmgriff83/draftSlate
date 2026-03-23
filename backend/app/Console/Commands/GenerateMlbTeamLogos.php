<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateMlbTeamLogos extends Command
{
    protected $signature = 'logos:mlb';

    protected $description = 'Generate MLB team logo JSON map from statsapi.mlb.com';

    public function handle(): int
    {
        $this->info('Fetching MLB teams...');

        $response = Http::timeout(30)->get('https://statsapi.mlb.com/api/v1/teams', [
            'sportId' => 1,
        ]);

        if ($response->failed()) {
            $this->error('Failed to fetch MLB teams.');
            return self::FAILURE;
        }

        $apiTeams = $response->json('teams', []);

        if (empty($apiTeams)) {
            $this->error('No teams returned from MLB API.');
            return self::FAILURE;
        }

        $teams = [];

        foreach ($apiTeams as $team) {
            $id = $team['id'] ?? null;
            $name = $team['name'] ?? null;
            $abbreviation = $team['abbreviation'] ?? null;

            if (!$id || !$name) {
                continue;
            }

            $teams[$name] = [
                'url' => "https://www.mlbstatic.com/team-logos/{$id}.svg",
                'abbreviation' => $abbreviation,
            ];
        }

        $json = json_encode([
            'generated_at' => now()->toIso8601String(),
            'count' => count($teams),
            'teams' => $teams,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk = Storage::disk(config('draftslate.headshots.disk'));
        $disk->put('logos/mlb.json', $json);

        $this->info('Generated MLB team logo map: ' . count($teams) . ' teams.');

        return self::SUCCESS;
    }
}
