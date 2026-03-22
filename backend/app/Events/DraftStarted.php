<?php

namespace App\Events;

use App\Models\DraftState;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DraftState $draftState,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftStarted';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("draft.{$this->draftState->league_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $league = $this->draftState->league;
        $memberships = $league->memberships()->with('user')->get();

        return [
            'draft_id' => $this->draftState->id,
            'week' => $this->draftState->week,
            'status' => $this->draftState->status,
            'draft_order' => $this->draftState->draft_order,
            'current_drafter_id' => $this->draftState->current_drafter_id,
            'current_round' => $this->draftState->current_round,
            'total_rounds' => $this->draftState->total_rounds,
            'pick_timer_seconds' => $league->pick_timer_seconds,
            'timer_started_at' => $this->draftState->current_pick_started_at?->toISOString(),
            'members' => $memberships->map(fn ($m) => [
                'id' => $m->id,
                'team_name' => $m->team_name,
                'user_name' => $m->user->display_name,
            ]),
        ];
    }
}
