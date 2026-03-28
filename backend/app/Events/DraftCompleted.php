<?php

namespace App\Events;

use App\Models\DraftState;
use App\Models\SlatePick;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DraftState $draftState,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftCompleted';
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("draft.{$this->draftState->league_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        // Build final rosters
        $picks = SlatePick::where('slate_pool_id', $this->draftState->slate_pool_id)
            ->with(['membership', 'pickSelection'])
            ->get()
            ->groupBy('league_membership_id');

        $rosters = [];
        foreach ($picks as $membershipId => $memberPicks) {
            $rosters[] = [
                'membership_id' => $membershipId,
                'team_name' => $memberPicks->first()->membership->team_name,
                'picks' => $memberPicks->map(fn ($p) => [
                    'description' => $p->pickSelection->description,
                    'position' => $p->position,
                    'slot_number' => $p->slot_number,
                    'drafted_odds' => $p->drafted_odds,
                ])->values(),
            ];
        }

        return [
            'draft_id' => $this->draftState->id,
            'week' => $this->draftState->week,
            'completed_at' => $this->draftState->completed_at?->toISOString(),
            'final_rosters' => $rosters,
        ];
    }
}
