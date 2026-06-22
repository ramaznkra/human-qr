<?php

namespace App\Services;

use App\Events\OrderStatusUpdated;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableCall;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryTaskService
{
    /**
     * @return array<string, mixed>
     */
    public function markItemStatus(int $itemId, string $status): array
    {
        if (! in_array(session('admin_role'), [User::ROLE_ADMIN, User::ROLE_CASHIER], true)) {
            throw ValidationException::withMessages(['auth' => 'Bu işlem için kasa yetkisi gerekir.']);
        }

        if (! in_array($status, [OrderItem::STATUS_PREPARING, OrderItem::STATUS_READY], true)) {
            throw ValidationException::withMessages(['status' => 'Geçersiz hazırlık durumu.']);
        }

        return DB::transaction(function () use ($itemId, $status) {
            $item = OrderItem::query()
                ->with(['order.table'])
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $item->order) {
                abort(404);
            }

            $item->update(['preparation_status' => $status]);

            $task = null;
            if ($status === OrderItem::STATUS_READY) {
                $task = $this->ensureDeliveryTaskForItem($item);
            }

            $this->syncOrderStatusFromItems($item->order);

            return [
                'item' => $this->itemPayload($item->fresh(['product'])),
                'task' => $task ? $this->taskPayload($task->fresh(['orderItem', 'table', 'assignedUser'])) : null,
                'order' => $this->orderStatusPayload($item->order->fresh()),
            ];
        });
    }

    public function ensureDeliveryTaskForItem(OrderItem $item): ?DeliveryTask
    {
        $item->loadMissing(['order.table']);
        $order = $item->order;

        if (! $order || ! $order->table_id) {
            return null;
        }

        $existing = DeliveryTask::query()
            ->where('order_item_id', $item->id)
            ->where('type', DeliveryTask::TYPE_DELIVER_ITEM)
            ->open()
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $assignedUserId = $order->table?->assigned_user_id;

        return DeliveryTask::create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'table_id' => $order->table_id,
            'assigned_user_id' => $assignedUserId,
            'type' => DeliveryTask::TYPE_DELIVER_ITEM,
            'status' => $assignedUserId ? DeliveryTask::STATUS_ASSIGNED : DeliveryTask::STATUS_PENDING,
        ]);
    }

    public function createBillRequestTask(TableCall $call): ?DeliveryTask
    {
        if (! $call->isBill()) {
            return null;
        }

        return DB::transaction(function () use ($call) {
            $order = Order::query()
                ->where('table_id', $call->table_id)
                ->whereIn('status', [Order::STATUS_READY, Order::STATUS_DELIVERED])
                ->whereNull('payment_method')
                ->orderByDesc('updated_at')
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return null;
            }

            $existing = DeliveryTask::query()
                ->where('order_id', $order->id)
                ->where('type', DeliveryTask::TYPE_BILL_REQUEST)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return DeliveryTask::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'order_item_id' => null,
                'table_id' => $order->table_id,
                'assigned_user_id' => null,
                'type' => DeliveryTask::TYPE_BILL_REQUEST,
                'status' => DeliveryTask::STATUS_PENDING,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function acceptTask(DeliveryTask $task): array
    {
        $userId = $this->waiterUserId();

        return DB::transaction(function () use ($task, $userId) {
            $task = DeliveryTask::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($task->status === DeliveryTask::STATUS_COMPLETED) {
                return $this->taskPayload($task);
            }

            if ($task->assigned_user_id && (int) $task->assigned_user_id !== $userId) {
                throw ValidationException::withMessages(['task' => 'Bu görev başka bir garsona atanmış.']);
            }

            $task->update([
                'assigned_user_id' => $userId,
                'status' => DeliveryTask::STATUS_ACCEPTED,
                'accepted_at' => $task->accepted_at ?? now(),
            ]);

            return $this->taskPayload($task->fresh(['orderItem', 'table', 'assignedUser']));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function completeTask(DeliveryTask $task): array
    {
        $userId = $this->waiterUserId();

        return DB::transaction(function () use ($task, $userId) {
            $task = DeliveryTask::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($task->assigned_user_id && (int) $task->assigned_user_id !== $userId) {
                throw ValidationException::withMessages(['task' => 'Bu görevi yalnızca üstlenen garson tamamlayabilir.']);
            }

            if (! $task->assigned_user_id) {
                $task->update([
                    'assigned_user_id' => $userId,
                    'accepted_at' => $task->accepted_at ?? now(),
                ]);
            }

            if ($task->type === DeliveryTask::TYPE_DELIVER_ITEM && $task->orderItem) {
                $task->orderItem->update(['preparation_status' => OrderItem::STATUS_SERVED]);
            }

            $task->update([
                'status' => DeliveryTask::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            if ($task->order) {
                $this->syncOrderStatusFromItems($task->order);
            }

            return $this->taskPayload($task->fresh(['orderItem', 'table', 'assignedUser']));
        });
    }

    public function syncOrderStatusFromItems(Order $order): void
    {
        $order->loadMissing('items');
        $items = $order->items->reject(fn (OrderItem $item) => $item->preparation_status === OrderItem::STATUS_CANCELLED);

        if ($items->isEmpty() || $order->payment_method !== null) {
            return;
        }

        $statuses = $items->pluck('preparation_status')->map(fn ($status) => $status ?: OrderItem::STATUS_WAITING);
        $newStatus = null;

        if ($statuses->every(fn ($status) => $status === OrderItem::STATUS_SERVED)) {
            $newStatus = Order::STATUS_DELIVERED;
        } elseif ($statuses->every(fn ($status) => $status === OrderItem::STATUS_READY || $status === OrderItem::STATUS_SERVED)) {
            $newStatus = Order::STATUS_READY;
        } elseif ($statuses->contains(OrderItem::STATUS_PREPARING) || $statuses->contains(OrderItem::STATUS_READY) || $statuses->contains(OrderItem::STATUS_SERVED)) {
            $newStatus = Order::STATUS_PREPARING;
        }

        if ($newStatus && $order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
            $order->refresh();
            event(OrderStatusUpdated::fromOrder($order));
            if ($newStatus === Order::STATUS_DELIVERED) {
                app(TableStatusService::class)->sync($order->table_id);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function taskPayload(DeliveryTask $task): array
    {
        $task->loadMissing(['orderItem', 'table', 'assignedUser']);

        return [
            'id' => $task->id,
            'type' => $task->type,
            'type_label' => $this->taskTypeLabel($task->type),
            'status' => $task->status,
            'status_label' => $this->taskStatusLabel($task->status),
            'order_id' => $task->order_id,
            'order_item_id' => $task->order_item_id,
            'table_id' => $task->table_id,
            'table' => $task->table?->number,
            'assigned_user_id' => $task->assigned_user_id,
            'assigned_user_name' => $task->assignedUser?->name,
            'item_name' => $task->orderItem?->product_name,
            'quantity' => $task->orderItem?->quantity,
            'created_at' => $task->created_at?->format('H:i'),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'sort_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'preparation_status' => $item->preparation_status,
            'preparation_status_label' => $this->itemStatusLabel($item->preparation_status),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderStatusPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => $order->status_label,
            'payment_method' => $order->payment_method,
        ];
    }

    private function waiterUserId(): int
    {
        if (session('admin_role') !== User::ROLE_WAITER) {
            throw ValidationException::withMessages(['auth' => 'Bu işlem için garson yetkisi gerekir.']);
        }

        return (int) session('admin_user_id');
    }

    private function itemStatusLabel(?string $status): string
    {
        return match ($status) {
            OrderItem::STATUS_PREPARING => 'Hazırlanıyor',
            OrderItem::STATUS_READY => 'Hazır',
            OrderItem::STATUS_SERVED => 'Teslim Edildi',
            OrderItem::STATUS_CANCELLED => 'İptal',
            default => 'Bekliyor',
        };
    }

    private function taskTypeLabel(string $type): string
    {
        return match ($type) {
            DeliveryTask::TYPE_DELIVER_ITEM => 'Teslim Görevi',
            DeliveryTask::TYPE_CUSTOMER_CALL => 'Müşteri Çağrısı',
            DeliveryTask::TYPE_BILL_REQUEST => 'Hesap Talebi',
            DeliveryTask::TYPE_COLLECT_EMPTY => 'Boş Toplama',
            DeliveryTask::TYPE_TABLE_CLEANUP => 'Masa Temizliği',
            default => $type,
        };
    }

    private function taskStatusLabel(string $status): string
    {
        return match ($status) {
            DeliveryTask::STATUS_ASSIGNED => 'Garsona Atandı',
            DeliveryTask::STATUS_ACCEPTED => 'Teslim Alındı',
            DeliveryTask::STATUS_COMPLETED => 'Tamamlandı',
            DeliveryTask::STATUS_CANCELLED => 'İptal',
            default => 'Bekliyor',
        };
    }
}
