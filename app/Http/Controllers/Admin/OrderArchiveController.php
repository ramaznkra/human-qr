<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use App\Services\OrderArchiveExportService;
use App\Support\OrderArchiveFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Response;

class OrderArchiveController extends Controller
{
    public function __construct(
        private OrderArchiveExportService $exportService,
    ) {}

    public function index(Request $request): View
    {
        $query = OrderArchiveFilter::apply($request);
        $orders = (clone $query)->paginate(20)->withQueryString();
        $filteredTotal = (clone $query)->count();
        $summary = $this->exportService->summarize($query);
        $exportDay = $this->exportService->resolveExportDay($request);

        return view('admin.orders.archive', [
            'orders' => $orders,
            'filteredTotal' => $filteredTotal,
            'summary' => $summary,
            'exportDay' => $exportDay,
            'exportDayLabel' => $exportDay->format('d.m.Y'),
            'tables' => Table::query()->orderByNumber()->get(['id', 'number']),
        ]);
    }

    public function export(Request $request, string $mode): Response
    {
        abort_unless(in_array($mode, ['daily', 'report'], true), 404);

        return $this->exportService->export($request, $mode);
    }

    public function purge(Request $request): RedirectResponse
    {
        $query = OrderArchiveFilter::apply($request);
        $count = (clone $query)->count();

        if ($count === 0) {
            return back()->with('error', 'Mevcut filtreye uyan silinecek adisyon yok.');
        }

        $ids = (clone $query)->pluck('id');
        Order::query()->whereIn('id', $ids)->delete();

        return redirect()
            ->route('admin.orders.archive')
            ->with('success', "{$count} adisyon arşivden temizlendi.");
    }

    public function destroy(Order $order): RedirectResponse
    {
        if (! in_array($order->status, Order::archivedStatuses(), true)) {
            return back()->with('error', 'Yalnızca tamamlanmış veya iptal edilmiş adisyonlar silinebilir.');
        }

        $number = $order->order_number;
        $order->delete();

        return redirect()
            ->route('admin.orders.archive', request()->only(['q', 'status', 'table_id', 'date_from', 'date_to', 'page']))
            ->with('success', "#{$number} adisyonu silindi.");
    }
}
