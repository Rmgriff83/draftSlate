<?php

namespace App\Jobs;

use App\Models\DraftState;
use App\Models\LeagueMembership;
use App\Services\DraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DraftAutoPickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 10;

    public function __construct(
        public int $draftStateId,
        public int $drafterId,
        public int $expectedPickIndex,
    ) {
        $this->onQueue('draft-high');
    }

    public function handle(DraftService $draftService): void
    {
        $draft = DraftState::find($this->draftStateId);

        if (!$draft || $draft->status !== 'active') {
            return;
        }

        // Verify we're still on the expected pick (drafter may have already picked)
        if ($draft->current_pick_index !== $this->expectedPickIndex) {
            Log::info("DraftAutoPickJob: Pick already made, skipping", [
                'draft_id' => $this->draftStateId,
                'expected_index' => $this->expectedPickIndex,
                'current_index' => $draft->current_pick_index,
            ]);
            return;
        }

        if ($draft->current_drafter_id !== $this->drafterId) {
            return;
        }

        $drafter = LeagueMembership::find($this->drafterId);
        if (!$drafter) {
            return;
        }

        try {
            $draftService->autoPickForDrafter($draft, $drafter);
            Log::info("DraftAutoPickJob: Auto-picked for drafter {$this->drafterId} in draft {$this->draftStateId}");
            // Next auto-pick is scheduled by DraftService::advanceDraft()
        } catch (\Exception $e) {
            Log::error("DraftAutoPickJob: Failed", [
                'draft_id' => $this->draftStateId,
                'drafter_id' => $this->drafterId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
