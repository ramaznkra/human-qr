<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\Money;
use App\Support\OrderArchiveFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderArchiveExportService
{
    public function summarize(Builder $query): array
    {
        $paid = (clone $query)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNotNull('payment_method');

        $netRevenue = Money::normalize((clone $paid)->sum('total'));
        $cashRevenue = Money::normalize((clone $paid)->where('payment_method', Order::PAYMENT_CASH)->sum('total'));
        $cardRevenue = Money::normalize((clone $paid)->where('payment_method', Order::PAYMENT_CARD)->sum('total'));

        return [
            'total_records' => (clone $query)->count(),
            'paid_orders' => (clone $paid)->count(),
            'cancelled_orders' => (clone $query)->where('status', Order::STATUS_CANCELLED)->count(),
            'net_revenue' => $netRevenue,
            'cash_revenue' => $cashRevenue,
            'card_revenue' => $cardRevenue,
            'net_revenue_formatted' => $this->formatMoney($netRevenue),
            'cash_revenue_formatted' => $this->formatMoney($cashRevenue),
            'card_revenue_formatted' => $this->formatMoney($cardRevenue),
        ];
    }

    public function export(Request $request, string $mode): Response
    {
        $mode = $mode === 'daily' ? 'daily' : 'report';
        $baseQuery = OrderArchiveFilter::apply($request);

        if ($mode === 'daily') {
            $day = $this->resolveExportDay($request);
            $query = (clone $baseQuery)->whereDate('created_at', $day);
            $periodLabel = $day->format('d.m.Y');
            $filename = 'arsiv-gunluk-'.$day->format('Y-m-d').'.pdf';
            $title = 'Günlük Arşiv Raporu';
        } else {
            $query = $baseQuery;
            $periodLabel = $this->periodLabel($request);
            $filename = 'arsiv-rapor-'.now()->format('Y-m-d-His').'.pdf';
            $title = 'Özet & Liste Raporu';
        }

        $summary = $this->summarize($query);
        $orders = $query->get();
        $settings = Setting::allCached();

        $pdf = Pdf::loadView('admin.orders.archive-pdf', [
            'venueName' => $settings['venue_name'],
            'title' => $title,
            'mode' => $mode,
            'periodLabel' => $periodLabel,
            'summary' => $summary,
            'orders' => $orders,
            'generatedAt' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function resolveExportDay(Request $request): Carbon
    {
        $from = $request->input('date_from');
        $to = $request->input('date_to');

        if ($from && $to && $from === $to) {
            return Carbon::parse($from)->startOfDay();
        }

        if ($from && ! $to) {
            return Carbon::parse($from)->startOfDay();
        }

        if ($to && ! $from) {
            return Carbon::parse($to)->startOfDay();
        }

        return Carbon::today();
    }

    private function periodLabel(Request $request): string
    {
        $from = $request->input('date_from');
        $to = $request->input('date_to');

        if ($from && $to) {
            return Carbon::parse($from)->format('d.m.Y').' – '.Carbon::parse($to)->format('d.m.Y');
        }

        if ($from) {
            return Carbon::parse($from)->format('d.m.Y').' ve sonrası';
        }

        if ($to) {
            return Carbon::parse($to)->format('d.m.Y').' ve öncesi';
        }

        return 'Tüm arşiv';
    }

    private function formatMoney(string|float $amount): string
    {
        return number_format(Money::toFloat($amount), 2, ',', '.').' ₺';
    }
}
