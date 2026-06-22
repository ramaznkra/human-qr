<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Support\OrderStationFlags;

class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order, public bool $silent = false)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('orders.'.$this->order->restaurant_id)];
    }

    public function broadcastAs(): string
    {
        return 'OrderCreated';
    }

    public function broadcastWith(): array
    {
        $order = $this->order->loadMissing([
            'table:id,number',
            'items:id,order_id,product_id,product_name,quantity,notes',
            'items.product:id,type,category_id',
            'items.product.category:id,type',
        ]);

        $items = $order->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->product_name,
            'quantity' => $item->quantity,
            'notes' => $item->notes,
            'type' => $item->product?->stationType() ?? 'kitchen',
        ]);

        $types = $items->pluck('type')->unique();

        return [
            'silent' => $this->silent,
            'order' => array_merge([
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'source' => $order->source ?? Order::SOURCE_QR,
                'source_label' => $order->source_label,
                'is_waiter_order' => $order->isWaiterOrder(),
                'payment_method' => $order->payment_method,
                'table' => $order->table?->number,
                'notes' => $order->notes,
                'total' => $order->total,
                'created_at' => $order->created_at?->format('H:i'),
                'created_at_iso' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'items' => $items->values()->all(),
            ], OrderStationFlags::fromTypes($types)),
        ];
    }
}
