<?php

namespace App\Jobs;

use App\Events\PickGraded;
use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResultGradingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public ?int $slatePoolId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(ScoringService $scoring): void
    {
        $query = PickSelection::where('outcome', 'pending')
            ->where('game_time', '<', now())
            ->whereNotNull('result_data');

        if ($this->slatePoolId) {
            $query->where('slate_pool_id', $this->slatePoolId);
        }

        $picks = $query->get();

        if ($picks->isEmpty()) {
            return;
        }

        $affectedLeagueWeeks = collect();
        $gradedCount = 0;

        foreach ($picks as $pick) {
            $previousOutcome = $pick->outcome;
            $scoring->gradePick($pick);

            // Only count if outcome actually changed
            $pick->refresh();
            if ($pick->outcome !== $previousOutcome) {
                $gradedCount++;
            } else {
                continue; // No change, skip matchup tracking
            }

            // Find affected league+week combos for matchup scoring
            $slatePicks = SlatePick::where('pick_selection_id', $pick->id)->get();

            foreach ($slatePicks as $slatePick) {
                $membership = $slatePick->membership;

                if ($membership) {
                    $key = $membership->league_id . ':' . $slatePick->week;
                    $affectedLeagueWeeks[$key] = [
                        'league_id' => $membership->league_id,
                        'week' => $slatePick->week,
                    ];

                    event(new PickGraded($membership->league_id, $pick->id, $pick->outcome, $pick->description));
                }
            }
        }

        // Dispatch MatchupScoreJob for each affected league+week
        foreach ($affectedLeagueWeeks as $combo) {
            MatchupScoreJob::dispatch($combo['league_id'], $combo['week']);
        }

        Log::info("ResultGradingJob: Graded {$gradedCount} picks");
    }
}
