<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderStatusUpdated;
use App\Events\TableCallForwarded;
use App\Events\TableCallUpdated;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableCall;
use App\Services\CompletedLiveOrderCleanup;
use App\Services\DeliveryTaskService;
use App\Services\TableStatusService;
use App\Support\CurrentRestaurant;
use App\Support\OrderStationFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LiveOrdersController extends Controller
{
    public function index(): View
    {
        return view('admin.live-orders.index', $this->liveOrdersViewData());
    }

    /** Mutfak / bar / nargile operasyon tableti (tam ekran). ?station=kitchen|bar|nargile ile filtre. */
    public function screen(Request $request): View
    {
        $station = in_array($request->query('station'), ['kitchen', 'bar', 'nargile'], true)
            ? $request->query('station')
            : 'all';

        return view('admin.live-orders.screen', array_merge(
            $this->liveOrdersViewData(),
            ['defaultStation' => $station],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function liveOrdersViewData(): array
    {
        return [
            'tables' => Table::query()->orderByNumber()->get(['id', 'number', 'is_active', 'status']),
            'busyTableIds' => Table::busyTableIds(),
            'defaultStation' => 'all',
            'settings' => \App\Models\Setting::allCached(),
            'categories' => Category::query()
                ->active()
                ->get(['id', 'name', 'slug', 'type', 'icon']),
            'kasaLogo' => \App\Support\SiteBranding::logoUrl(),
        ];
    }

    /**
     * Tek endpoint: mutfak + bar siparişleri (ürün type bilgisiyle).
     */
    public function liveOrders(Request $request, CompletedLiveOrderCleanup $cleanup): JsonResponse
    {
        $this->purgeStaleCompleted($cleanup);

        $isKasa = $request->boolean('kasa');
        $liveLimit = min(80, max(1, (int) $request->query('live_limit', $isKasa ? 10 : 80)));
        $completedLimit = min(40, max(0, (int) $request->query('completed_limit', $isKasa ? 0 : 40)));
        $callsLimit = min(50, max(1, (int) $request->query('calls_limit', $isKasa ? 12 : 50)));

        $mapOrder = function (Order $order): array {
            $items = $order->items->map(function ($item) {
                $type = $item->product?->stationType() ?? 'kitchen';
                $task = $item->deliveryTasks
                    ->first(fn (DeliveryTask $task) => $task->type === DeliveryTask::TYPE_DELIVER_ITEM
                        && in_array($task->status, [DeliveryTask::STATUS_PENDING, DeliveryTask::STATUS_ASSIGNED, DeliveryTask::STATUS_ACCEPTED], true));

                return [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'notes' => $item->notes,
                    'options' => $item->options ?? [],
                    'type' => $type,
                    'station' => $type,
                    'preparation_status' => $item->preparation_status ?? 'waiting',
                    'preparation_status_label' => $this->itemStatusLabel($item->preparation_status ?? 'waiting'),
                    'delivery_task_status' => $task?->status,
                    'delivery_task_assigned_user_id' => $task?->assigned_user_id,
                ];
            });

            $types = $items->pluck('type')->unique();

            return array_merge([
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'source' => $order->source ?? Order::SOURCE_QR,
                'source_label' => $order->source_label,
                'is_waiter_order' => $order->isWaiterOrder(),
                'payment_method' => $order->payment_method,
                'table' => $order->table?->number,
                'kasa_served_through_item_id' => $order->kasa_served_through_item_id,
                'notes' => $order->notes,
                'total' => $order->total,
                'created_at' => $order->created_at->format('H:i'),
                'created_at_iso' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
                'items' => $items->values(),
            ], OrderStationFlags::fromTypes($types));
        };

        $orders = Order::query()
            ->select(['id', 'order_number', 'status', 'source', 'notes', 'total', 'table_id', 'payment_method', 'kasa_served_through_item_id', 'created_at', 'updated_at'])
            ->with([
                'table:id,number',
                'items' => fn ($q) => $q->select(['id', 'order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'notes', 'preparation_status', 'options']),
                'items.product:id,type,station,category_id',
                'items.product.category:id,type',
                'items.deliveryTasks:id,order_item_id,type,status,assigned_user_id',
            ])
            ->live()
            ->orderByDesc('created_at')
            ->limit($liveLimit)
            ->get()
            ->map($mapOrder);

        $focusTableId = $request->filled('focus_table_id') ? (int) $request->query('focus_table_id') : null;
        $focusOrderId = $request->filled('focus_order_id') ? (int) $request->query('focus_order_id') : null;

        if ($focusTableId) {
            $focusedQuery = Order::query()
                ->select(['id', 'order_number', 'status', 'source', 'notes', 'total', 'table_id', 'payment_method', 'kasa_served_through_item_id', 'created_at', 'updated_at'])
                ->with([
                    'table:id,number',
                    'items' => fn ($q) => $q->select(['id', 'order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'notes', 'preparation_status', 'options']),
                    'items.product:id,type,station,category_id',
                    'items.product.category:id,type',
                    'items.deliveryTasks:id,order_item_id,type,status,assigned_user_id',
                ])
                ->live()
                ->where('table_id', $focusTableId);

            $focusedOrder = null;

            if ($focusOrderId) {
                $focusedOrder = (clone $focusedQuery)->whereKey($focusOrderId)->first();
            }

            $focusedOrder ??= (clone $focusedQuery)->orderByDesc('id')->first();

            if ($focusedOrder) {
                $mapped = $mapOrder($focusedOrder);
                $orders = $orders
                    ->reject(fn (array $o) => (int) $o['id'] === (int) $mapped['id'])
                    ->prepend($mapped)
                    ->values();
            }
        }

        $completedOrders = $completedLimit > 0
            ? $cleanup->completedQuery()
                ->select(['id', 'order_number', 'status', 'source', 'notes', 'total', 'table_id', 'payment_method', 'kasa_served_through_item_id', 'created_at', 'updated_at'])
                ->with([
                    'table:id,number',
                    'items' => fn ($q) => $q->select(['id', 'order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'notes', 'preparation_status', 'options']),
                    'items.product:id,type,station,category_id',
                    'items.product.category:id,type',
                    'items.deliveryTasks:id,order_item_id,type,status,assigned_user_id',
                ])
                ->orderByDesc('updated_at')
                ->limit($completedLimit)
                ->get()
                ->map($mapOrder)
            : collect();

        $calls = TableCall::query()
            ->with(['linkedTable', 'waiter:id,name'])
            ->open()
            ->orderByDesc('created_at')
            ->limit($callsLimit)
            ->get()
            ->map(fn (TableCall $call) => TableCallUpdated::callPayload($call));

        $taskService = app(DeliveryTaskService::class);
        $deliveryTaskQuery = DeliveryTask::query()
            ->with(['orderItem', 'table', 'assignedUser'])
            ->open()
            ->orderByDesc('updated_at');

        if (session('admin_role') === 'waiter' && session('admin_user_id')) {
            $deliveryTaskQuery->visibleToWaiter((int) session('admin_user_id'));
        }

        $deliveryTasks = $deliveryTaskQuery
            ->limit(80)
            ->get()
            ->map(fn (DeliveryTask $task) => $taskService->taskPayload($task));

        $busyTableIds = Table::busyTableIds();

        $pendingApprovalTableIds = Order::query()
            ->whereNotNull('table_id')
            ->where('status', Order::STATUS_PENDING_APPROVAL)
            ->pluck('table_id');

        $awaitingPaymentTableIds = Order::query()
            ->whereNotNull('table_id')
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNull('payment_method')
            ->pluck('table_id');

        $tables = Table::query()
            ->orderByNumber()
            ->get(['id', 'number', 'is_active', 'status'])
            ->map(fn (Table $table) => [
                'id' => $table->id,
                'number' => $table->number,
                'is_active' => $table->is_active,
                'status' => $table->status,
                'is_busy' => $busyTableIds->contains($table->id),
                'pending_approval' => $pendingApprovalTableIds->contains($table->id),
                'awaiting_payment' => $awaitingPaymentTableIds->contains($table->id),
            ]);

        return response()->json([
            'orders' => $orders,
            'completed_orders' => $completedOrders,
            'calls' => $calls,
            'delivery_tasks' => $deliveryTasks,
            'tables' => $tables,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /** Kasa: garson veya hesap çağrısını garsona yönlendirir. */
    public function forwardCall(TableCall $call): JsonResponse
    {
        if ($call->status === TableCall::STATUS_COMPLETED) {
            return response()->json(['success' => false, 'message' => 'Çağrı kapatılmış.'], 422);
        }

        if (! $call->forwarded_to_waiter) {
            $call->update(['forwarded_to_waiter' => true]);
            event(new TableCallForwarded($call->fresh()));
        }

        if ($call->isBill()) {
            app(DeliveryTaskService::class)->createBillRequestTask($call);
        }

        $message = $call->type === TableCall::TYPE_WAITER
            ? 'Garson çağrısı garson paneline iletildi.'
            : 'Garsona iletildi. POS / masa bildirimi gönderildi.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function resolveCall(Request $request, TableCall $call): JsonResponse
    {
        $request->validate([
            'payment_method' => 'nullable|in:cash,card',
        ]);

        if ($call->status === TableCall::STATUS_COMPLETED) {
            return response()->json(['success' => true, 'message' => 'Çağrı zaten kapatılmış.']);
        }

        $call->update(['status' => TableCall::STATUS_COMPLETED]);

        $this->syncTable($call->table_id);

        event(new TableCallUpdated($call->fresh()));

        return response()->json(['success' => true, 'message' => 'Çağrı tamamlandı.']);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending_approval,pending,preparing,ready,delivered,cancelled',
            'payment_method' => 'nullable|in:cash,card',
            'payment_only' => 'nullable|boolean',
        ]);

        if ($request->boolean('payment_only')) {
            if ($order->isWaiterOrder()) {
                $order->update([
                    'status' => Order::STATUS_DELIVERED,
                    'payment_method' => $request->payment_method,
                ]);
            } else {
                if ($order->status !== Order::STATUS_DELIVERED) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ödeme yalnızca teslim edilmiş siparişlerde kaydedilir.',
                    ], 422);
                }

                $order->update(['payment_method' => $request->payment_method]);
            }

            $order->refresh();
            event(OrderStatusUpdated::fromOrder($order));
            $this->syncTable($order->table_id);

            return response()->json([
                'success' => true,
                'message' => 'Sipariş kapatıldı.',
                'status' => $order->status,
                'status_label' => $order->status_label,
                'payment_method' => $order->payment_method,
            ]);
        }

        $newStatus = $request->status;

        if (! $order->canTransitionTo($newStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu durum geçişi izinli değil. Sıra: Kabul (Hazırlanıyor) → Afiyet Olsun → Nakit/Kart ile kapat.',
            ], 422);
        }

        if ($newStatus === Order::STATUS_READY) {
            $taskService = app(DeliveryTaskService::class);
            $order->loadMissing('items');
            foreach ($order->items as $item) {
                if (! in_array($item->preparation_status, ['ready', 'served', 'cancelled'], true)) {
                    $item->update(['preparation_status' => 'ready']);
                }
                $taskService->ensureDeliveryTaskForItem($item);
            }
        }

        $order->update(['status' => $newStatus]);
        $order->refresh();
        event(OrderStatusUpdated::fromOrder($order));

        if (in_array($newStatus, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true) || $order->isClosed()) {
            $this->syncTable($order->table_id);
        }

        return response()->json([
            'success' => true,
            'status' => $order->status,
            'status_label' => $order->status_label,
        ]);
    }

    public function dismissCompleted(Order $order, CompletedLiveOrderCleanup $cleanup): JsonResponse
    {
        try {
            $cleanup->dismiss($order);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Adisyon canlı listeden kaldırıldı · admin arşivinde duruyor.',
        ]);
    }

    public function dismissAllCompleted(Request $request, CompletedLiveOrderCleanup $cleanup): JsonResponse
    {
        $tableId = $request->filled('table_id') ? (int) $request->table_id : null;
        $count = $cleanup->dismissAll($tableId);

        return response()->json([
            'success' => true,
            'message' => $count > 0
                ? "{$count} adisyon canlı listeden kaldırıldı."
                : 'Kaldırılacak tamamlanan adisyon yok.',
            'count' => $count,
        ]);
    }

    private function purgeStaleCompleted(CompletedLiveOrderCleanup $cleanup): void
    {
        $minutes = (int) config('live_orders.completed_retention_minutes', 120);

        if ($minutes <= 0) {
            return;
        }

        $restaurantId = CurrentRestaurant::id()
            ?? session('admin_restaurant_id')
            ?? session('kiosk_restaurant_id')
            ?? 0;

        Cache::remember(
            "live_orders:purge_completed:{$restaurantId}",
            300,
            function () use ($cleanup, $minutes) {
                $cleanup->purgeOlderThan($minutes);

                return true;
            },
        );
    }

    private function itemStatusLabel(string $status): string
    {
        return match ($status) {
            'preparing' => 'Hazırlanıyor',
            'ready' => 'Hazır',
            'served' => 'Teslim Edildi',
            'cancelled' => 'İptal',
            default => 'Bekliyor',
        };
    }

    private function syncTable(?int $tableId): void
    {
        if ($tableId !== null) {
            app(TableStatusService::class)->sync($tableId);
        }
    }
}
