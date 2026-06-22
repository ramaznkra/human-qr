<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Canlı paneldeki tamamlanan adisyonları gizler; veritabanından silmez.
 * Admin arşivi tüm kayıtları görmeye devam eder.
 */
class CompletedLiveOrderCleanup
{
    public function completedQuery(): Builder
    {
        return Order::query()
            ->whereNull('dismissed_from_live_at')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('status', Order::STATUS_DELIVERED)
                        ->whereNotNull('payment_method');
                })->orWhere('status', Order::STATUS_CANCELLED);
            });
    }

    public function canDismiss(Order $order): bool
    {
        return ($order->status === Order::STATUS_DELIVERED && $order->payment_method !== null)
            || $order->status === Order::STATUS_CANCELLED;
    }

    public function dismiss(Order $order): void
    {
        if (! $this->canDismiss($order)) {
            throw ValidationException::withMessages([
                'order' => 'Yalnızca tamamlanmış veya iptal edilmiş adisyon kaldırılabilir.',
            ]);
        }

        $order->update(['dismissed_from_live_at' => now()]);
    }

    public function dismissAll(?int $tableId = null): int
    {
        $query = $this->completedQuery();

        if ($tableId !== null) {
            $query->where('table_id', $tableId);
        }

        return $query->update(['dismissed_from_live_at' => now()]);
    }

    public function purgeOlderThan(int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        $cutoff = now()->subMinutes($minutes);

        return $this->completedQuery()
            ->where('updated_at', '<', $cutoff)
            ->update(['dismissed_from_live_at' => now()]);
    }
}
