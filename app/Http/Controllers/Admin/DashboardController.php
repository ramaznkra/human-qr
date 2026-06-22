<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Services\DashboardFinanceStats;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardFinanceStats $financeStats): View
    {
        $finance = $financeStats->forToday();

        $stats = [
            'categories' => Category::count(),
            'products' => Product::count(),
            'tables' => Table::count(),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'pending_orders' => Order::whereIn('status', [Order::STATUS_PENDING_APPROVAL, Order::STATUS_PENDING])->count(),
        ];

        $recentOrders = Order::query()
            ->select(['id', 'order_number', 'status', 'source', 'total', 'table_id', 'created_at'])
            ->with(['table:id,number'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $topProducts = OrderItem::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(quantity * unit_price) as total_revenue'),
            )
            ->whereNotNull('product_id')
            ->whereHas('order', function ($q) {
                $q->whereDate('created_at', today())
                    ->where('status', Order::STATUS_DELIVERED);
            })
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->with('product:id,name,image')
            ->get();

        $hourlyTotals = array_fill(0, 24, 0.0);
        Order::query()
            ->whereDate('created_at', today())
            ->where('status', Order::STATUS_DELIVERED)
            ->get(['total', 'created_at'])
            ->each(function (Order $order) use (&$hourlyTotals) {
                $hourlyTotals[$order->created_at->hour] += Money::toFloat($order->total);
            });

        $salesTrend = collect(range(8, 23))->map(function (int $hour) use ($hourlyTotals) {
            $value = $hourlyTotals[$hour] ?? 0.0;

            return [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'value' => round($value, 2),
                'formatted' => number_format($value, 0, ',', '.'),
            ];
        })->values();

        $hourlyOrderCounts = array_fill(0, 24, 0);
        Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get(['created_at'])
            ->each(function (Order $order) use (&$hourlyOrderCounts) {
                $hourlyOrderCounts[$order->created_at->hour]++;
            });

        $busyTrend = collect(range(8, 23))->map(function (int $hour) use ($hourlyOrderCounts) {
            $value = (int) ($hourlyOrderCounts[$hour] ?? 0);

            return [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'value' => $value,
            ];
        })->values();

        $peakBusy = $busyTrend->sortByDesc('value')->first();
        $busyPeakHour = $peakBusy && $peakBusy['value'] > 0
            ? $peakBusy['label']
            : '—';
        $busyPeakCount = (int) ($peakBusy['value'] ?? 0);
        $busyTotalOrders = (int) $busyTrend->sum('value');

        $lowStockProducts = Product::query()
            ->where('in_stock', false)
            ->with('category:id,name')
            ->orderBy('name')
            ->take(8)
            ->get(['id', 'name', 'category_id', 'in_stock', 'is_available']);

        $recentSalesLines = OrderItem::query()
            ->with(['order:id,order_number,created_at,status'])
            ->whereHas('order', fn ($q) => $q->whereDate('created_at', today()))
            ->orderByDesc('id')
            ->take(12)
            ->get(['id', 'order_id', 'product_name', 'quantity', 'unit_price', 'options', 'created_at']);

        return view('admin.dashboard', compact(
            'stats',
            'recentOrders',
            'finance',
            'topProducts',
            'salesTrend',
            'busyTrend',
            'busyPeakHour',
            'busyPeakCount',
            'busyTotalOrders',
            'lowStockProducts',
            'recentSalesLines',
        ));
    }
}
