<?php

namespace App\Jobs;

use App\Events\MatchupScored;
use App\Models\Matchup;
use App\Models\SlatePick;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchupScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $leagueId,
        public int $week,
    ) {
        $this->onQueue('default');
    }

    public function handle(ScoringService $scoring): void
    {
        $matchups = Matchup::where('league_id', $this->leagueId)
            ->where('week', $this->week)
            ->where('status', 'in_progress')
            ->get();

        $completedCount = 0;

        foreach ($matchups as $matchup) {
            // Check if ALL starter picks for both teams are graded
            if (!$this->allStartersGraded($matchup)) {
                continue;
            }

            $scoring->scoreMatchup($matchup);
            $completedCount++;

            event(new MatchupScored(
                $this->leagueId,
                $matchup->id,
                $matchup->home_score,
                $matchup->away_score,
                $matchup->winner_id,
            ));
        }

        // Update standings after any matchup completes (idempotent recompute)
        if ($completedCount > 0) {
            StandingsUpdateJob::dispatch($this->leagueId, $this->week);
        }

        if ($completedCount > 0) {
            Log::info("MatchupScoreJob: Scored {$completedCount} matchups for league {$this->leagueId} week {$this->week}");
        }
    }

    private function allStartersGraded(Matchup $matchup): bool
    {
        foreach ([$matchup->home_team_id, $matchup->away_team_id] as $teamId) {
            $pendingStarters = SlatePick::where('league_membership_id', $teamId)
                ->where('week', $this->week)
                ->where('position', 'starter')
                ->whereHas('pickSelection', function ($q) {
                    $q->where('outcome', 'pending');
                })
                ->count();

            if ($pendingStarters > 0) {
                return false;
            }
        }

        return true;
    }
}
