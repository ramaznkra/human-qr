<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderAdminController extends Controller
{
    public function index(Request $request): View
    {
        // Liste yalnızca masa + sipariş başlık bilgisini gösterir; ürün satırları
        // detay sayfasında yüklenir. Gereksiz item hidrasyonunu önlemek için yalnızca
        // ihtiyaç duyulan ilişki (table) eager-load edilir.
        $query = Order::with(['table:id,number'])->orderByDesc('created_at');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        $orders = $query->paginate(20);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'table']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:pending_approval,pending,preparing,ready,delivered,cancelled',
            'payment_method' => 'nullable|in:cash,card',
        ]);

        $payload = ['status' => $request->status];
        if ($request->status === Order::STATUS_DELIVERED && $request->filled('payment_method')) {
            $payload['payment_method'] = $request->payment_method;
        }

        $order->update($payload);
        $order->refresh();
        event(OrderStatusUpdated::fromOrder($order));

        return back()->with('success', 'Sipariş durumu güncellendi.');
    }
}
