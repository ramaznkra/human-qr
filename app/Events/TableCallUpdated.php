<?php

namespace App\Events;

use App\Models\TableCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableCallUpdated implements ShouldBroadcast
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
        return 'TableCallUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'call' => self::callPayload($this->call->loadMissing(['linkedTable:id,number,uuid', 'waiter:id,name'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function callPayload(TableCall $call): array
    {
        $call->loadMissing(['linkedTable:id,number,uuid', 'waiter:id,name']);

        return [
            'id' => $call->id,
            'kind' => 'call',
            'type' => $call->type,
            'type_label' => $call->type_label,
            'headline' => $call->headline,
            'table' => $call->tableNumber(),
            'table_uuid' => $call->linkedTable?->uuid,
            'table_token' => $call->linkedTable?->uuid ?? $call->linkedTable?->qr_token,
            'status' => $call->status,
            'is_bill' => $call->isBill(),
            'forwarded_to_waiter' => (bool) $call->forwarded_to_waiter,
            'waiter_id' => $call->waiter_id,
            'waiter_name' => $call->waiter?->name,
            'created_at' => $call->created_at?->format('H:i'),
            'updated_at' => $call->updated_at?->toIso8601String(),
            'sort_at' => $call->updated_at?->toIso8601String(),
        ];
    }
}
