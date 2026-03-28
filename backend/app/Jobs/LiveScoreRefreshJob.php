<?php

namespace App\Jobs;

use App\Events\ScoresUpdated;
use App\Models\PickSelection;
use App\Services\OddsApiService;
use App\Services\SportsDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LiveScoreRefreshJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $picks = PickSelection::where('outcome', 'pending')
            ->where('is_drafted', true)
            ->where('game_time', '<=', now())
            ->where('game_time', '>', now()->subHours(48))
            ->get();

        if ($picks->isEmpty()) {
            return;
        }

        // Skip picks where result_data already marks the game as completed,
        // UNLESS it's a player prop still missing stats (needs backfill)
        $picks = $picks->filter(function ($pick) {
            $resultData = $pick->result_data ?? [];
            if (empty($resultData['completed'])) {
                return true; // game still in progress
            }
            // Keep completed player props that are missing current_stat
            return $pick->pick_type === 'player_prop' && !isset($resultData['current_stat']);
        });

        if ($picks->isEmpty()) {
            return;
        }

        $oddsApi = app(OddsApiService::class);
        $sportsData = app(SportsDataService::class);
        $totalScores = 0;
        $totalStats = 0;

        // Track updated picks per league for broadcasting
        $updatedByLeague = [];

        $grouped = $picks->groupBy('sport');

        // Pre-fetch NBA box scores for all dates with pending player prop picks
        $nbaBoxScores = [];
        if ($grouped->has('basketball_nba')) {
            $nbaPicks = $grouped->get('basketball_nba');
            $propDates = $nbaPicks
                ->filter(fn($p) => $p->pick_type === 'player_prop')
                ->pluck('game_time')
                ->map(fn($gt) => \Illuminate\Support\Carbon::parse($gt)->toDateString())
                ->unique();

            $today = now()->toDateString();
            foreach ($propDates as $date) {
                if ($date === $today) {
                    $nbaBoxScores = array_merge($nbaBoxScores, $sportsData->fetchNbaBoxScoresLive());
                } else {
                    $nbaBoxScores = array_merge($nbaBoxScores, $sportsData->fetchNbaBoxScoresForDate($date));
                }
            }
        }

        foreach ($grouped as $sport => $sportPicks) {
            $scores = $oddsApi->fetchScoresLive($sport);

            foreach ($sportPicks as $pick) {
                $eventId = explode('_', $pick->external_id)[0];
                $existing = $pick->result_data ?? [];
                $changed = false;

                // Game scores
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
                    $changed = true;
                    $totalScores++;
                }

                // Player stats for NBA props — fetch when in-progress (live updates) OR
                // when completed but missing stats (game finished between runs)
                $isCompleted = !empty($existing['completed']);
                $needsStats = empty($existing['current_stat']);
                if ((!$isCompleted || $needsStats) && $sport === 'basketball_nba' && $pick->pick_type === 'player_prop' && !empty($nbaBoxScores)) {
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
                            'not_playing_reason' => $playerStats['not_playing_reason'] ?? null,
                        ];
                        $existing['period'] = $playerStats['period'];
                        $existing['game_status'] = $playerStats['status'];

                        $statKey = $sportsData->categoryToStatKey($pick->category);
                        if ($statKey && isset($playerStats[$statKey])) {
                            $existing['current_stat'] = $playerStats[$statKey];
                            $existing['stat_label'] = $statKey;
                        }
                        $changed = true;
                        $totalStats++;
                    }
                }

                if ($changed) {
                    $pick->refresh();
                    if ($pick->outcome === 'pending') {
                        $pick->update(['result_data' => $existing]);

                        // Collect for broadcast — get league_id via slate_pool
                        $leagueId = $pick->slatePool?->league_id;
                        if ($leagueId) {
                            $updatedByLeague[$leagueId][] = [
                                'pick_selection_id' => $pick->id,
                                'result_data' => $existing,
                            ];
                        }
                    }
                }
            }
        }

        // Broadcast per league
        foreach ($updatedByLeague as $leagueId => $pickData) {
            event(new ScoresUpdated($leagueId, $pickData));
        }

        Log::info("LiveScoreRefreshJob: scores={$totalScores}, player_stats={$totalStats}, leagues_notified=" . count($updatedByLeague));
    }
}
