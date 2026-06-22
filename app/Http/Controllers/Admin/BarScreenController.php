<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarScreenController extends Controller
{
    public function index(): View
    {
        return view('admin.bar.index');
    }

    public function orders(): JsonResponse
    {
        $orders = Order::query()
            ->select(['id', 'order_number', 'status', 'notes', 'table_id', 'created_at'])
            ->with([
                'table:id,number',
                'items' => fn ($q) => $q->select(['id', 'order_id', 'product_id', 'product_name', 'quantity', 'notes']),
                'items.product:id,category_id',
                'items.product.category:id,slug,name',
            ])
            ->whereIn('status', ['preparing', 'ready'])
            ->whereHas('items', function ($q) {
                $q->whereHas('product.category', fn ($c) => $c->where('slug', 'icecek'));
            })
            ->orderBy('created_at')
            ->get()
            ->map(function ($order) {
                $drinks = $order->items->filter(
                    fn ($item) => $item->product?->category?->slug === 'icecek'
                )->values();

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'table' => $order->table?->number,
                    'notes' => $order->notes,
                    'created_at' => $order->created_at->format('H:i'),
                    'items' => $drinks->map(fn ($i) => [
                        'name' => $i->product_name,
                        'quantity' => $i->quantity,
                        'notes' => $i->notes,
                    ]),
                ];
            });

        return response()->json(['orders' => $orders, 'timestamp' => now()->toIso8601String()]);
    }

    public function markReady(Order $order): JsonResponse
    {
        if (! in_array($order->status, ['preparing'], true)) {
            return response()->json(['success' => false, 'message' => 'Sipariş zaten işlendi.'], 422);
        }

        $order->update(['status' => 'ready']);
        $order->refresh();
        event(OrderStatusUpdated::fromOrder($order));

        return response()->json(['success' => true, 'status' => 'ready', 'status_label' => $order->status_label]);
    }
}
