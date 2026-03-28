<?php

namespace App\Events;

use App\Models\DraftState;
use App\Models\LeagueMembership;
use App\Models\SlatePick;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DraftPickMade implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DraftState $draftState,
        public SlatePick $slatePick,
        public LeagueMembership $drafter,
        public bool $isAutoPick = false,
    ) {}

    public function broadcastAs(): string
    {
        return 'DraftPickMade';
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("draft.{$this->draftState->league_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $pick = $this->slatePick->pickSelection;

        return [
            'drafter_id' => $this->drafter->id,
            'drafter_team' => $this->drafter->team_name,
            'pick' => [
                'id' => $this->slatePick->id,
                'pick_selection_id' => $pick->id,
                'description' => $pick->description,
                'pick_type' => $pick->pick_type,
                'sport' => $pick->sport,
                'category' => $pick->category,
                'player_name' => $pick->player_name,
                'home_team' => $pick->home_team,
                'away_team' => $pick->away_team,
                'game_display' => $pick->game_display,
                'snapshot_odds' => $pick->snapshot_odds,
                'drafted_odds' => $this->slatePick->drafted_odds,
                'position' => $this->slatePick->position,
                'slot_number' => $this->slatePick->slot_number,
                'slot_type' => $this->slatePick->slot_type,
            ],
            'round' => $this->slatePick->draft_round,
            'pick_number' => $this->slatePick->draft_pick_number,
            'is_auto_pick' => $this->isAutoPick,
        ];
    }
}
