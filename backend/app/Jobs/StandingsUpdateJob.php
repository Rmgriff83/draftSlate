<?php

namespace App\Jobs;

use App\Events\StandingsUpdated;
use App\Models\League;
use App\Models\Matchup;
use App\Services\PlayoffBracketService;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StandingsUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $leagueId,
        public int $week = 0,
    ) {
        $this->onQueue('default');
    }

    public function handle(ScoringService $scoring, PlayoffBracketService $bracketService): void
    {
        $league = League::find($this->leagueId);

        if (!$league) {
            return;
        }

        // Use explicit week if provided, otherwise fall back to current_week
        $week = $this->week ?: $league->current_week;
        $isPlayoffWeek = $week >= $league->getPlayoffStartWeek();

        // Update standings for the completed week (regular season only affects W/L/T)
        $scoring->updateStandings($league, $week);

        // Calculate and apply rankings (only recalculate seeds during regular season)
        if (!$isPlayoffWeek) {
            $rankings = $scoring->calculateRankings($league);

            $standingsData = [];

            foreach ($rankings as $rank => $membership) {
                $membership->update(['playoff_seed' => $rank + 1]);

                $totalGames = $membership->wins + $membership->losses + $membership->ties;

                $standingsData[] = [
                    'rank' => $rank + 1,
                    'membership_id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'team_name' => $membership->team_name,
                    'user_name' => $membership->user->display_name ?? null,
                    'wins' => $membership->wins,
                    'losses' => $membership->losses,
                    'ties' => $membership->ties,
                    'total_correct_picks' => $membership->total_correct_picks,
                    'playoff_seed' => $rank + 1,
                    'final_position' => $membership->final_position,
                    'win_percentage' => $totalGames > 0
                        ? round(($membership->wins + ($membership->ties * 0.5)) / $totalGames, 3)
                        : 0,
                ];
            }

            event(new StandingsUpdated($this->leagueId, $week, $standingsData));
        }

        Log::info("StandingsUpdateJob: Updated standings for league {$this->leagueId} week {$week}");

        // Only advance week / transition state when ALL matchups for the week are completed
        $remainingMatchups = Matchup::where('league_id', $league->id)
            ->where('week', $week)
            ->where('status', '!=', 'completed')
            ->count();

        if ($remainingMatchups > 0) {
            Log::info("StandingsUpdateJob: {$remainingMatchups} matchups still pending for league {$this->leagueId} week {$week}");
            return;
        }

        $league->refresh();
        $totalMatchups = $league->total_matchups ?? 14;
        $totalWeeks = $league->getTotalWeeksIncludingPlayoffs();

        if ($week === $league->current_week) {
            if (!$isPlayoffWeek && $week < $totalMatchups) {
                // Regular season, not the last week → advance
                $league->increment('current_week');
                Log::info("StandingsUpdateJob: Advanced league {$this->leagueId} to week " . ($league->current_week));
            } elseif (!$isPlayoffWeek && $week >= $totalMatchups && $league->state === 'active') {
                // Final regular season week → generate playoff bracket
                $bracketService->generateBracket($league);
                Log::info("StandingsUpdateJob: Generated playoff bracket for league {$this->leagueId}");
            } elseif ($isPlayoffWeek && $week < $totalWeeks) {
                // Playoff week, not the final one → advance bracket + increment week
                $bracketService->advanceBracket($league, $week);
                $league->increment('current_week');
                Log::info("StandingsUpdateJob: Advanced playoff bracket for league {$this->leagueId}");
            } elseif ($isPlayoffWeek && $week >= $totalWeeks) {
                // Final playoff week → advance bracket (which calls finalizeSeason)
                $bracketService->advanceBracket($league, $week);
                Log::info("StandingsUpdateJob: Finalized season for league {$this->leagueId}");
            }
        }
    }
}
