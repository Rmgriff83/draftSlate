<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchupScored implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $leagueId,
        public int $matchupId,
        public int $homeScore,
        public int $awayScore,
        public ?int $winnerId,
    ) {}

    public function broadcastAs(): string
    {
        return 'MatchupScored';
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
            'matchup_id' => $this->matchupId,
            'scores' => [
                'home' => $this->homeScore,
                'away' => $this->awayScore,
            ],
            'winner_id' => $this->winnerId,
        ];
    }
}
