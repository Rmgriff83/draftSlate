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
        // Fetch only picks within actionable time window (live + upcoming 2h)
        $picks = PickSelection::where('outcome', 'pending')
            ->where('is_drafted', true)
            ->where('game_time', '>', now()->subHours(6))
            ->where('game_time', '<=', now()->addHours(2))
            ->get();

        if ($picks->isEmpty()) {
            return;
        }

        // Partition into live games vs upcoming (pre-game)
        [$livePicks, $upcomingPicks] = $picks->partition(
            fn ($pick) => $pick->game_time <= now()
        );

        $oddsApi = app(OddsApiService::class);
        $sportsData = app(SportsDataService::class);
        $totalRefreshed = 0;
        $totalScores = 0;
        $totalStats = 0;
        $totalSkipped = 0;

        // Track completed event IDs so we skip redundant odds/stats fetches
        $completedEventIds = [];

        // --- Process LIVE picks: scores + odds + player stats ---
        $liveGrouped = $livePicks->groupBy('sport');

        // Pre-fetch box scores for NBA if we have live NBA picks
        $nbaBoxScores = [];
        if ($liveGrouped->has('basketball_nba')) {
            $nbaBoxScores = $sportsData->fetchNbaBoxScores();
        }

        foreach ($liveGrouped as $sport => $sportPicks) {
            $eventIds = $sportPicks->pluck('external_id')
                ->map(fn ($id) => explode('_', $id)[0])
                ->unique()
                ->values()
                ->toArray();

            if (empty($eventIds)) {
                continue;
            }

            // Fetch scores first so we can detect completed games
            $scores = $oddsApi->fetchScores($sport);

            // Identify completed events
            foreach ($scores as $eventId => $scoreData) {
                if (!empty($scoreData['completed']) && $scoreData['home_score'] !== null) {
                    $completedEventIds[] = $eventId;
                }
            }

            // Only fetch odds for non-completed events
            $activeEventIds = array_diff($eventIds, $completedEventIds);
            $oddsMap = [];
            $pointsMap = [];

            if (!empty($activeEventIds)) {
                $result = $oddsApi->fetchCurrentOddsMap($activeEventIds, $sport);
                $oddsMap = $result['odds'];
                $pointsMap = $result['points'];
            }

            foreach ($sportPicks as $pick) {
                $eventId = explode('_', $pick->external_id)[0];
                $isCompleted = in_array($eventId, $completedEventIds);

                // --- Refresh odds (skip completed games) ---
                if (!$isCompleted && isset($oddsMap[$pick->external_id])) {
                    if ($pick->pick_type === 'player_prop') {
                        $apiPoint = $pointsMap[$pick->external_id] ?? null;
                        $originalPoint = $this->extractPointFromDescription($pick->description);

                        if ($apiPoint !== null && $originalPoint !== null && abs($apiPoint - $originalPoint) > 0.01) {
                            goto updateScores;
                        }
                    }

                    $pick->update([
                        'current_odds' => $oddsMap[$pick->external_id],
                        'odds_updated_at' => now(),
                    ]);
                    $totalRefreshed++;
                } elseif ($isCompleted) {
                    $totalSkipped++;
                }

                updateScores:
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

                // Player stats for props (NBA) — skip if game completed
                if (!$isCompleted && $sport === 'basketball_nba' && $pick->pick_type === 'player_prop' && !empty($nbaBoxScores)) {
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

                        $statKey = $sportsData->categoryToStatKey($pick->category);
                        if ($statKey && isset($playerStats[$statKey])) {
                            $existing['current_stat'] = $playerStats[$statKey];
                            $existing['stat_label'] = $statKey;
                        }
                        $totalStats++;
                    }
                }

                if (!empty($existing)) {
                    $pick->refresh();
                    if ($pick->outcome === 'pending') {
                        $pick->update(['result_data' => $existing]);
                    }
                }
            }
        }

        // --- Process UPCOMING picks: odds only (no scores, no stats) ---
        $upcomingGrouped = $upcomingPicks->groupBy('sport');

        foreach ($upcomingGrouped as $sport => $sportPicks) {
            $eventIds = $sportPicks->pluck('external_id')
                ->map(fn ($id) => explode('_', $id)[0])
                ->unique()
                ->values()
                ->toArray();

            if (empty($eventIds)) {
                continue;
            }

            $result = $oddsApi->fetchCurrentOddsMap($eventIds, $sport);
            $oddsMap = $result['odds'];
            $pointsMap = $result['points'];

            foreach ($sportPicks as $pick) {
                if (!isset($oddsMap[$pick->external_id])) {
                    continue;
                }

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
        }

        Log::info("OddsRefreshJob: odds={$totalRefreshed}, scores={$totalScores}, player_stats={$totalStats}, skipped_completed={$totalSkipped}");
    }

    private function extractPointFromDescription(string $description): ?float
    {
        if (preg_match('/(?:Over|Under)\s+([\d.]+)/i', $description, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
