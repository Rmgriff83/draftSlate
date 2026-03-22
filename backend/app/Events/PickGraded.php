<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickGraded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $leagueId,
        public int $pickSelectionId,
        public string $outcome,
        public string $description,
    ) {}

    public function broadcastAs(): string
    {
        return 'PickGraded';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("league.{$this->leagueId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'pick_selection_id' => $this->pickSelectionId,
            'outcome' => $this->outcome,
            'description' => $this->description,
        ];
    }
}
