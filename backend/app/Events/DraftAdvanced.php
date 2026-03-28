<?php

namespace App\Events;

use App\Models\DraftState;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftAdvanced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DraftState $draftState,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftAdvanced';
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("draft.{$this->draftState->league_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $isAutodraft = $this->draftState->isInAutoDraft($this->draftState->current_drafter_id);

        return [
            'current_drafter_id' => $this->draftState->current_drafter_id,
            'current_round' => $this->draftState->current_round,
            'current_pick_index' => $this->draftState->current_pick_index,
            'timer_started_at' => $this->draftState->current_pick_started_at?->toISOString(),
            'pick_timer_seconds' => $isAutodraft
                ? config('draftslate.draft.autodraft_delay_seconds', 3)
                : ($this->draftState->league->pick_timer_seconds ?? 60),
            'auto_draft_members' => $this->draftState->auto_draft_members ?? [],
        ];
    }
}
