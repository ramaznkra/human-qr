<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TableCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function live(): JsonResponse
    {
        $orders = Order::query()
            ->select(['id', 'order_number', 'status', 'total', 'table_id', 'created_at', 'updated_at'])
            ->with(['table:id,number'])
            ->live()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'status_label' => $o->status_label,
                'table' => $o->table?->number,
                'total' => $o->total,
                'created_at' => $o->created_at->format('H:i'),
                'updated_at' => $o->updated_at->toIso8601String(),
            ]);

        $calls = TableCall::query()
            ->with(['linkedTable'])
            ->active()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'type_label' => $c->type_label,
                'headline' => $c->headline,
                'table' => $c->tableNumber(),
                'created_at' => $c->created_at->format('H:i'),
            ]);

        return response()->json([
            'orders' => $orders,
            'calls' => $calls,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function acknowledgeCall(TableCall $call): JsonResponse
    {
        $call->update(['status' => TableCall::STATUS_COMPLETED]);

        return response()->json(['success' => true]);
    }
}
