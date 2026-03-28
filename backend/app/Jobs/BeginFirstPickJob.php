<?php

namespace App\Jobs;

use App\Models\DraftState;
use App\Services\DraftService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BeginFirstPickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $draftStateId,
        public string $expectedDraftStartsAt,
    ) {
        $this->onQueue('draft-high');
    }

    public function handle(DraftService $draftService): void
    {
        $draft = DraftState::find($this->draftStateId);
        if (!$draft) {
            return;
        }

        // Guard: status must still be active
        if ($draft->status !== 'active') {
            Log::info("BeginFirstPickJob: Draft {$this->draftStateId} is no longer active, skipping");
            return;
        }

        // Guard: draft_starts_at must match (stale-job protection)
        if ($draft->draft_starts_at?->toISOString() !== $this->expectedDraftStartsAt) {
            Log::info("BeginFirstPickJob: draft_starts_at mismatch for draft {$this->draftStateId}, skipping");
            return;
        }

        // Guard: picks must not have already started
        if ($draft->current_pick_started_at !== null) {
            Log::info("BeginFirstPickJob: Picks already started for draft {$this->draftStateId}, skipping");
            return;
        }

        Log::info("BeginFirstPickJob: Beginning picks for draft {$this->draftStateId}");

        $draftService->beginFirstPick($draft);
    }
}
