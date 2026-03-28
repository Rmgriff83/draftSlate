<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayoffRoundCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $leagueId,
        public int $week,
        public string $format,
    ) {}

    public function broadcastAs(): string
    {
        return 'PlayoffRoundCompleted';
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
            'league_id' => $this->leagueId,
            'week' => $this->week,
            'format' => $this->format,
        ];
    }
}
