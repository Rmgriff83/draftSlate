<?php

namespace App\Jobs;

use App\Models\DraftState;
use App\Models\League;
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
        // Find leagues that need drafts:
        // 1. Active leagues with current_week > 0
        // 2. Full pending leagues with season_start_date set (for week 1 auto-trigger)
        $leagues = League::where(function ($q) {
            $q->whereIn('state', ['active', 'playoffs'])->where('current_week', '>', 0);
        })->orWhere(function ($q) {
            $q->where('state', 'pending')->whereNotNull('season_start_date');
        })->get();

        foreach ($leagues as $league) {
            // For pending leagues, verify the league is full before launching
            if ($league->state === 'pending') {
                if (!$league->isFull()) {
                    continue;
                }
            }

            // Determine the next week needing a draft from actual draft history
            $latestCompletedWeek = DraftState::where('league_id', $league->id)
                ->where('status', 'completed')
                ->max('week') ?? 0;

            $nextWeek = $latestCompletedWeek + 1;

            if ($nextWeek > $league->getTotalWeeksIncludingPlayoffs()) {
                continue; // Season is done
            }

            // Skip if an active or lobby draft already exists for this week
            $existingDraft = DraftState::where('league_id', $league->id)
                ->where('week', $nextWeek)
                ->whereIn('status', ['active', 'completed'])
                ->first();

            if ($existingDraft) {
                continue;
            }

            // Calculate next draft time for this league
            $nextDraftTime = $league->getNextDraftTime();
            if (!$nextDraftTime) {
                continue;
            }

            // Launch draft when it's time
            if (now()->gte($nextDraftTime)) {
                Log::info("CheckAndDispatchSlatePoolBuilds: Dispatching LaunchDraftJob for league {$league->id} week {$nextWeek}");
                LaunchDraftJob::dispatch($league->id, $nextWeek);
            }
        }

        // Recovery: find active drafts where countdown expired but picks never started
        $stuckDrafts = DraftState::where('status', 'active')
            ->whereNotNull('draft_starts_at')
            ->where('draft_starts_at', '<=', now())
            ->whereNull('current_pick_started_at')
            ->get();

        foreach ($stuckDrafts as $draft) {
            Log::warning("CheckAndDispatchSlatePoolBuilds: Recovering stuck draft {$draft->id}, dispatching BeginFirstPickJob");
            BeginFirstPickJob::dispatch($draft->id, $draft->draft_starts_at->toISOString());
        }
    }
}
