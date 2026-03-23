<?php

namespace App\Jobs;

use App\Models\League;
use App\Models\SlatePool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckAndDispatchSlatePoolBuilds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $minutesBefore = config('draftslate.odds_api.pool_build_minutes_before_draft', 30);

        // Find active leagues that need pools built
        $leagues = League::where('state', 'active')
            ->where('current_week', '>', 0)
            ->get();

        foreach ($leagues as $league) {
            $week = $league->current_week;

            // Check if a pool already exists for this week
            $poolExists = SlatePool::where('league_id', $league->id)
                ->where('week', $week)
                ->exists();

            if ($poolExists) {
                continue;
            }

            // Calculate next draft time for this league
            $nextDraftTime = $league->getNextDraftTime();
            if (!$nextDraftTime) {
                continue;
            }

            // Check if we're within the build window
            $buildTime = $nextDraftTime->copy()->subMinutes($minutesBefore);

            if (now()->gte($buildTime) && now()->lte($nextDraftTime)) {
                Log::info("CheckAndDispatchSlatePoolBuilds: Dispatching build for league {$league->id} week {$week}");
                SlatePoolBuildJob::dispatch($league->id, $week);
            }
        }
    }

}
