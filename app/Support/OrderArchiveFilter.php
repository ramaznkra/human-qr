<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderArchiveFilter
{
    public static function apply(Request $request): Builder
    {
        $query = Order::query()
            ->archived()
            ->with(['table:id,number'])
            ->orderByDesc('created_at');

        if ($request->filled('status') && in_array($request->status, Order::archivedStatuses(), true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', (int) $request->table_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('q')) {
            $term = '%'.trim($request->q).'%';
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                    ->orWhereHas('table', fn ($t) => $t->where('number', 'like', $term));
            });
        }

        return $query;
    }
}
