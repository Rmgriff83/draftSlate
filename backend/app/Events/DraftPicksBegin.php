<?php

namespace App\Events;

use App\Models\DraftState;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftPicksBegin implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DraftState $draftState,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftPicksBegin';
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("draft.{$this->draftState->league_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $league = $this->draftState->league;

        return [
            'draft_id' => $this->draftState->id,
            'current_drafter_id' => $this->draftState->current_drafter_id,
            'current_pick_started_at' => $this->draftState->current_pick_started_at?->toISOString(),
            'pick_timer_seconds' => $league->pick_timer_seconds,
        ];
    }
}
