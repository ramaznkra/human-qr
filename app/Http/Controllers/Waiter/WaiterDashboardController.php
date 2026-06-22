<?php

namespace App\Http\Controllers\Waiter;

use App\Events\OrderStatusUpdated;
use App\Events\TableCallUpdated;
use App\Http\Controllers\Controller;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TableCall;
use App\Services\DeliveryTaskService;
use App\Services\TableStatusService;
use App\Services\TableTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WaiterDashboardController extends Controller
{
    public function index(): View
    {
        $settings = Setting::allCached();

        return view('waiter.dashboard', [
            'venueName' => $settings['venue_name'],
            'venueTagline' => $settings['venue_tagline'],
            'waiterName' => session('admin_name'),
        ]);
    }

    /** QR siparişini onayla → mutfağa düşer (preparing). */
    public function approveOrder(Order $order): JsonResponse
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== Order::STATUS_PENDING_APPROVAL) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => 'Bu sipariş başka bir personel tarafından zaten kabul edildi!',
                ], 409);
            }

            $order->update(['status' => Order::STATUS_PREPARING]);
            $order->refresh();

            if ($order->table_id) {
                app(TableStatusService::class)->markOccupied($order->table_id);
            }

            event(OrderStatusUpdated::fromOrder($order));

            return response()->json([
                'success' => true,
                'message' => 'Sipariş onaylandı, mutfağa iletildi.',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                ],
            ]);
        });
    }

    /** Aktif masadaki siparişleri boş masaya taşır. */
    public function transferTable(Request $request, TableTransferService $transfer): JsonResponse
    {
        $validated = $request->validate([
            'from_table_id' => 'required|integer|min:1',
            'to_table_id' => 'required|integer|min:1',
        ]);

        $result = $transfer->transfer(
            (int) $validated['from_table_id'],
            (int) $validated['to_table_id'],
        );

        $parts = [];
        if ($result['orders'] > 0) {
            $parts[] = $result['orders'].' sipariş';
        }
        if ($result['calls'] > 0) {
            $parts[] = $result['calls'].' çağrı';
        }

        return response()->json([
            'success' => true,
            'message' => implode(' ve ', $parts).' yeni masaya aktarıldı.',
            'moved_orders' => $result['orders'],
            'moved_calls' => $result['calls'],
        ]);
    }

    /** Garson çağrısını üstlenir — diğer garsonlar anında görür. */
    public function claimCall(TableCall $call): JsonResponse
    {
        $userId = (int) session('admin_user_id');

        return DB::transaction(function () use ($call, $userId) {
            $call = TableCall::query()
                ->whereKey($call->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($call->status === TableCall::STATUS_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Çağrı zaten kapatılmış.',
                ], 422);
            }

            if ($call->status === TableCall::STATUS_IN_PROGRESS) {
                if ((int) $call->waiter_id === $userId) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Çağrı size atandı.',
                        'call' => TableCallUpdated::callPayload($call->loadMissing(['linkedTable:id,number', 'waiter:id,name'])),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => 'Bu çağrı başka bir personel tarafından zaten kabul edildi!',
                    'call' => TableCallUpdated::callPayload($call->loadMissing(['linkedTable:id,number', 'waiter:id,name'])),
                ], 409);
            }

            if ($call->status !== TableCall::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => 'Bu çağrı başka bir personel tarafından zaten kabul edildi!',
                    'call' => TableCallUpdated::callPayload($call->loadMissing(['linkedTable:id,number', 'waiter:id,name'])),
                ], 409);
            }

            $call->update([
                'status' => TableCall::STATUS_IN_PROGRESS,
                'waiter_id' => $userId,
            ]);
            $call->refresh();
            event(new TableCallUpdated($call));

            return response()->json([
                'success' => true,
                'message' => 'Çağrı size atandı.',
                'call' => TableCallUpdated::callPayload($call->loadMissing(['linkedTable:id,number', 'waiter:id,name'])),
            ]);
        });
    }

    /** Garson: çağrıyı tek dokunuşla tamamla. */
    public function resolveCall(TableCall $call): JsonResponse
    {
        $userId = (int) session('admin_user_id');

        return DB::transaction(function () use ($call, $userId) {
            $call = TableCall::query()
                ->whereKey($call->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($call->status === TableCall::STATUS_COMPLETED) {
                return response()->json(['success' => true, 'message' => 'Çağrı zaten kapatılmış.']);
            }

            $call->update([
                'status' => TableCall::STATUS_COMPLETED,
                'waiter_id' => $call->waiter_id ?? $userId,
            ]);

            app(TableStatusService::class)->sync($call->table_id);
            $call->refresh();
            event(new TableCallUpdated($call));

            return response()->json([
                'success' => true,
                'message' => 'Çağrı tamamlandı.',
            ]);
        });
    }

    /**
     * Garson: çağrı veya siparişi tek dokunuşla kapat (kasa ekranıyla senkron).
     */
    public function acceptTask(DeliveryTask $task, DeliveryTaskService $tasks): JsonResponse
    {
        try {
            $payload = $tasks->acceptTask($task);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Görev alındı.',
            'task' => $payload,
        ]);
    }

    public function completeTask(DeliveryTask $task, DeliveryTaskService $tasks): JsonResponse
    {
        try {
            $payload = $tasks->completeTask($task);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teslim edildi.',
            'task' => $payload,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:call,order',
            'id' => 'required|integer|min:1',
            'payment_method' => 'nullable|in:cash,card',
        ]);

        $tableStatus = app(TableStatusService::class);
        $userId = (int) session('admin_user_id');

        if ($validated['type'] === 'call') {
            $call = TableCall::query()->findOrFail($validated['id']);

            if ($call->status === TableCall::STATUS_COMPLETED) {
                return response()->json(['success' => true, 'message' => 'Zaten kapatılmış.']);
            }

            if ($call->status === TableCall::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Önce «İlgileniyorum» ile çağrıyı üstlenin.',
                ], 422);
            }

            if ((int) $call->waiter_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu çağrıyı yalnızca üstlenen garson tamamlayabilir.',
                ], 422);
            }

            $call->update(['status' => TableCall::STATUS_COMPLETED]);
            $tableStatus->sync($call->table_id);
            $call->refresh();
            event(new TableCallUpdated($call));

            return response()->json([
                'success' => true,
                'message' => 'Çağrı tamamlandı.',
            ]);
        }

        $order = Order::query()->findOrFail($validated['id']);

        $order->loadMissing('items');
        $allServed = $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => $item->preparation_status === 'served');

        if ($order->status === Order::STATUS_READY && $allServed) {
            $order->update(['status' => Order::STATUS_DELIVERED]);
            $order->refresh();
            event(OrderStatusUpdated::fromOrder($order));

            return response()->json([
                'success' => true,
                'message' => 'Sipariş masaya teslim edildi (Afiyet Olsun).',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Garson yalnızca merkezde hazır olan siparişi teslim edebilir.',
        ], 422);
    }
}
