<?php

namespace App\Jobs;

use App\Models\PickSelection;
use App\Models\SlatePick;
use App\Services\OddsMathService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SlateLockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(OddsMathService $oddsMath): void
    {
        // Find pick selections where game time has passed but picks aren't locked
        $selectionsToLock = PickSelection::where('game_time', '<=', now())
            ->where('is_drafted', true)
            ->whereHas('slatePicks', function ($query) {
                $query->where('is_locked', false);
            })
            ->get();

        $lockCount = 0;

        foreach ($selectionsToLock as $selection) {
            $unlockedPicks = $selection->slatePicks()
                ->where('is_locked', false)
                ->get();

            foreach ($unlockedPicks as $pick) {
                $lockedOdds = $selection->current_odds ?? $selection->snapshot_odds;
                $drift = $oddsMath->calculateOddsDrift($pick->drafted_odds, $lockedOdds);

                $pick->update([
                    'is_locked' => true,
                    'locked_at' => now(),
                    'locked_odds' => $lockedOdds,
                    'odds_drift' => $drift,
                ]);

                $lockCount++;
            }
        }

        if ($lockCount > 0) {
            Log::info("SlateLockJob: Locked {$lockCount} picks at game kickoff");
        }
    }
}
