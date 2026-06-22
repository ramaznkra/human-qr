<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuCatalogUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $restaurantId) {}

    public function broadcastOn(): array
    {
        return [new Channel('menu.'.$this->restaurantId)];
    }

    public function broadcastAs(): string
    {
        return 'MenuCatalogUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
