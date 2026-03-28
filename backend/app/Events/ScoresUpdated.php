<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoresUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $leagueId,
        public array $picks,
    ) {}

    public function broadcastAs(): string
    {
        return 'ScoresUpdated';
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
            'picks' => $this->picks,
        ];
    }
}
