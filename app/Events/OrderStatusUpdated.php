<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderId,
        public int $restaurantId,
        public string $status,
        public ?int $tableNumber = null,
        public ?string $orderNumber = null,
        public array $items = [],
        public ?string $paymentMethod = null,
        public ?string $statusLabel = null,
        public ?string $source = null,
    ) {
    }

    public static function fromOrder(Order $order): self
    {
        $order->loadMissing('table:id,number', 'items:id,order_id,product_name,quantity');

        return new self(
            orderId: (int) $order->id,
            restaurantId: (int) $order->restaurant_id,
            status: (string) $order->status,
            tableNumber: $order->table?->number ? (int) $order->table->number : null,
            orderNumber: $order->order_number,
            items: $order->items
                ->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                ])
                ->values()
                ->all(),
            paymentMethod: $order->payment_method,
            statusLabel: $order->status_label,
            source: $order->source,
        );
    }

    public function broadcastOn(): array
    {
        return [new Channel('orders.'.$this->restaurantId)];
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'source' => $this->source,
            'table' => $this->tableNumber,
            'order_number' => $this->orderNumber,
            'items' => $this->items,
            'payment_method' => $this->paymentMethod,
        ];
    }
}
