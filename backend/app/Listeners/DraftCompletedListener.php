<?php

namespace App\Listeners;

use App\Events\DraftCompleted;
use App\Jobs\GenerateMatchupsJob;

class DraftCompletedListener
{
    public function handle(DraftCompleted $event): void
    {
        $draftState = $event->draftState;

        GenerateMatchupsJob::dispatch(
            $draftState->league_id,
            $draftState->week,
        );
    }
}
