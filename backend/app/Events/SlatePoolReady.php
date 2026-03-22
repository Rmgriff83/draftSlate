<?php

namespace App\Events;

use App\Models\SlatePool;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlatePoolReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SlatePool $slatePool,
    ) {}

    public function broadcastAs(): string
    {
        return 'SlatePoolReady';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("draft.{$this->slatePool->league_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => 'ready',
            'slate_pool_id' => $this->slatePool->id,
            'week' => $this->slatePool->week,
            'pick_count' => $this->slatePool->pickSelections()->count(),
        ];
    }
}
