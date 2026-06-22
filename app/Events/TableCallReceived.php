<?php

namespace App\Events;

use App\Models\TableCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableCallReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TableCall $call)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('orders.'.$this->call->restaurant_id)];
    }

    public function broadcastAs(): string
    {
        return 'TableCallReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'call' => TableCallUpdated::callPayload($this->call),
        ];
    }
}
