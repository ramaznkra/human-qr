<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Table;
use App\Models\TableCall;

class TableStatusService
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_PAYMENT_PROCESSING = 'payment_processing';

    public function markOccupied(?int $tableId): void
    {
        if ($tableId === null) {
            return;
        }

        Table::query()
            ->whereKey($tableId)
            ->update(['status' => self::STATUS_OCCUPIED]);
    }

    /** Masa boşsa available, aksi halde occupied. */
    public function sync(?int $tableId): void
    {
        if ($tableId === null) {
            return;
        }

        $table = Table::query()->find($tableId);

        if (! $table) {
            return;
        }

        $hasLiveOrder = Order::query()
            ->where('table_id', $tableId)
            ->live()
            ->exists();

        $hasOpenCall = TableCall::query()
            ->where('table_id', $tableId)
            ->open()
            ->exists();

        if ($table->status === self::STATUS_PAYMENT_PROCESSING && ($hasLiveOrder || $hasOpenCall)) {
            return;
        }

        $status = ($hasLiveOrder || $hasOpenCall)
            ? self::STATUS_OCCUPIED
            : self::STATUS_AVAILABLE;

        $table->update(['status' => $status]);
    }
}
