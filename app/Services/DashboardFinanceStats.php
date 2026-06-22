<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TableCall;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardFinanceStats
{
    public function forToday(?Carbon $day = null): array
    {
        $day = $day ?? Carbon::today();

        $completedQuery = Order::query()
            ->whereDate('created_at', $day)
            ->where('status', Order::STATUS_DELIVERED);

        $dailyRevenue = Money::normalize((clone $completedQuery)->sum('total'));
        $completedCount = (clone $completedQuery)->count();

        $liveTableIds = Order::query()
            ->live()
            ->whereNotNull('table_id')
            ->pluck('table_id');

        $callTableIds = TableCall::query()
            ->active()
            ->pluck('table_id');

        $activeTables = $liveTableIds->merge($callTableIds)->unique()->filter()->count();

        $paymentRows = Order::query()
            ->whereDate('created_at', $day)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNotNull('payment_method')
            ->select('payment_method', DB::raw('count(*) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $cashCount = (int) ($paymentRows[Order::PAYMENT_CASH] ?? 0);
        $cardCount = (int) ($paymentRows[Order::PAYMENT_CARD] ?? 0);
        $paymentTotal = $cashCount + $cardCount;

        if ($paymentTotal > 0) {
            $cashPct = (int) round(($cashCount / $paymentTotal) * 100);
            $cardPct = 100 - $cashPct;
            $paymentSplit = "%{$cardPct} Kart · %{$cashPct} Nakit";
        } else {
            $paymentSplit = 'Kayıt bekleniyor';
        }

        $sourceRows = Order::query()
            ->whereDate('created_at', $day)
            ->where('status', Order::STATUS_DELIVERED)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source');

        $qrCount = (int) ($sourceRows[Order::SOURCE_QR] ?? 0);
        $waiterCount = (int) ($sourceRows[Order::SOURCE_WAITER] ?? 0);
        $sourceTotal = $qrCount + $waiterCount;

        if ($sourceTotal > 0) {
            $qrPct = (int) round(($qrCount / $sourceTotal) * 100);
            $waiterPct = 100 - $qrPct;
            $orderSourceSplit = "%{$qrPct} QR · %{$waiterPct} Garson";
        } else {
            $orderSourceSplit = 'Kayıt bekleniyor';
        }

        return [
            'daily_revenue' => $dailyRevenue,
            'daily_revenue_formatted' => number_format(Money::toFloat($dailyRevenue), 0, ',', '.').' ₺',
            'active_tables' => $activeTables,
            'completed_orders' => $completedCount,
            'payment_split' => $paymentSplit,
            'payment_cash_count' => $cashCount,
            'payment_card_count' => $cardCount,
            'order_source_split' => $orderSourceSplit,
            'order_qr_count' => $qrCount,
            'order_waiter_count' => $waiterCount,
        ];
    }
}
