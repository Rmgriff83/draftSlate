<?php

namespace App\Jobs;

use App\Models\PickSelection;
use App\Services\OddsApiService;
use App\Services\SportsDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OddsRefreshJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $picks = PickSelection::where('outcome', 'pending')
            ->where('is_drafted', true)
            ->where('game_time', '>', now()->subHours(6))
            ->get();

        if ($picks->isEmpty()) {
            return;
        }

        $oddsApi = app(OddsApiService::class);
        $sportsData = app(SportsDataService::class);
        $grouped = $picks->groupBy('sport');
        $totalRefreshed = 0;
        $totalScores = 0;
        $totalStats = 0;

        // Pre-fetch box scores for NBA if we have NBA picks
        $nbaBoxScores = [];
        if ($grouped->has('basketball_nba')) {
            $nbaBoxScores = $sportsData->fetchNbaBoxScores();
        }

        foreach ($grouped as $sport => $sportPicks) {
            $eventIds = $sportPicks->pluck('external_id')
                ->map(fn ($id) => explode('_', $id)[0])
                ->unique()
                ->values()
                ->toArray();

            if (empty($eventIds)) {
                continue;
            }

            // --- Refresh odds ---
            $result = $oddsApi->fetchCurrentOddsMap($eventIds, $sport);
            $oddsMap = $result['odds'];
            $pointsMap = $result['points'];

            foreach ($sportPicks as $pick) {
                if (!isset($oddsMap[$pick->external_id])) {
                    continue;
                }

                // For player props, skip if the bookmaker has shifted the line
                if ($pick->pick_type === 'player_prop') {
                    $apiPoint = $pointsMap[$pick->external_id] ?? null;
                    $originalPoint = $this->extractPointFromDescription($pick->description);

                    if ($apiPoint !== null && $originalPoint !== null && abs($apiPoint - $originalPoint) > 0.01) {
                        continue;
                    }
                }

                $pick->update([
                    'current_odds' => $oddsMap[$pick->external_id],
                    'odds_updated_at' => now(),
                ]);
                $totalRefreshed++;
            }

            // --- Refresh game scores ---
            $scores = $oddsApi->fetchScores($sport);

            foreach ($sportPicks as $pick) {
                $eventId = explode('_', $pick->external_id)[0];
                $existing = $pick->result_data ?? [];

                // Game scores from Odds API
                if (isset($scores[$eventId])) {
                    $scoreData = $scores[$eventId];
                    $existing = array_merge($existing, [
                        'home_team' => $scoreData['home_team'],
                        'away_team' => $scoreData['away_team'],
                        'home_score' => $scoreData['home_score'],
                        'away_score' => $scoreData['away_score'],
                        'completed' => $scoreData['completed'],
                        'last_update' => $scoreData['last_update'],
                    ]);
                    $totalScores++;
                }

                // --- Player stats for props (NBA) ---
                if ($sport === 'basketball_nba' && $pick->pick_type === 'player_prop' && !empty($nbaBoxScores)) {
                    $playerStats = $sportsData->findPlayerStats(
                        $nbaBoxScores,
                        $pick->player_name ?? '',
                        $pick->home_team ?? '',
                        $pick->away_team ?? ''
                    );

                    if ($playerStats) {
                        $existing['player_stats'] = [
                            'points' => $playerStats['points'],
                            'rebounds' => $playerStats['rebounds'],
                            'assists' => $playerStats['assists'],
                            'threes' => $playerStats['threes'],
                            'minutes' => $playerStats['minutes'],
                        ];
                        $existing['period'] = $playerStats['period'];
                        $existing['game_status'] = $playerStats['status'];

                        // Map the relevant stat to a top-level "current_stat" for easy frontend access
                        $statKey = $sportsData->categoryToStatKey($pick->category);
                        if ($statKey && isset($playerStats[$statKey])) {
                            $existing['current_stat'] = $playerStats[$statKey];
                            $existing['stat_label'] = $statKey;
                        }
                        $totalStats++;
                    }
                }

                // Always write if we have data — scores and stats change frequently
                // Re-check outcome to avoid overwriting result_data on already-graded picks
                // (a concurrent ResultGradingJob may have graded this pick since our initial query)
                if (!empty($existing)) {
                    $pick->refresh();
                    if ($pick->outcome === 'pending') {
                        $pick->update(['result_data' => $existing]);
                    }
                }
            }
        }

        Log::info("OddsRefreshJob: odds={$totalRefreshed}, scores={$totalScores}, player_stats={$totalStats}");
    }

    private function extractPointFromDescription(string $description): ?float
    {
        if (preg_match('/(?:Over|Under)\s+([\d.]+)/i', $description, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
