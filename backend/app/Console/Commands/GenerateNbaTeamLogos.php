<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateNbaTeamLogos extends Command
{
    protected $signature = 'logos:nba';

    protected $description = 'Generate NBA team logo JSON map from NBA schedule data';

    public function handle(): int
    {
        $this->info('Fetching NBA schedule to extract teams...');

        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Referer' => 'https://www.nba.com/',
                'Accept' => 'application/json',
            ])
            ->get('https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json');

        if ($response->failed()) {
            $this->error('Failed to fetch NBA schedule data.');
            return self::FAILURE;
        }

        $games = $response->json('leagueSchedule.gameDates', []);

        if (empty($games)) {
            $this->error('No game dates found in NBA schedule.');
            return self::FAILURE;
        }

        $teams = [];

        foreach ($games as $gameDate) {
            foreach ($gameDate['games'] ?? [] as $game) {
                foreach (['homeTeam', 'awayTeam'] as $side) {
                    $team = $game[$side] ?? null;
                    if (!$team) {
                        continue;
                    }

                    $teamId = $team['teamId'] ?? null;
                    $teamCity = $team['teamCity'] ?? '';
                    $teamName = $team['teamName'] ?? '';
                    $tricode = $team['teamTricode'] ?? null;

                    if (!$teamId || !$teamName) {
                        continue;
                    }

                    $fullName = trim("{$teamCity} {$teamName}");

                    if (!isset($teams[$fullName])) {
                        $teams[$fullName] = [
                            'url' => "https://cdn.nba.com/logos/nba/{$teamId}/primary/L/logo.svg",
                            'abbreviation' => $tricode,
                        ];
                    }
                }
            }
        }

        if (empty($teams)) {
            $this->error('No teams extracted from NBA schedule.');
            return self::FAILURE;
        }

        $json = json_encode([
            'generated_at' => now()->toIso8601String(),
            'count' => count($teams),
            'teams' => $teams,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk = Storage::disk(config('draftslate.headshots.disk'));
        $disk->put('logos/nba.json', $json);

        $this->info('Generated NBA team logo map: ' . count($teams) . ' teams.');

        return self::SUCCESS;
    }
}
