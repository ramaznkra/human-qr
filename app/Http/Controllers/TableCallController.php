<?php

namespace App\Http\Controllers;

use App\Events\TableCallReceived;
use App\Models\Table;
use App\Models\TableCall;
use App\Services\DeliveryTaskService;
use App\Services\TableStatusService;
use App\Support\CurrentRestaurant;
use App\Support\MenuLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TableCallController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        MenuLocale::apply($request, MenuLocale::resolve($request));

        $validated = $request->validate([
            'table_token' => 'required|string',
            'type' => 'nullable|in:waiter,bill,bill_cash,bill_card',
        ]);

        $table = $this->resolveTable($validated['table_token']);
        if (! $table) {
            return response()->json([
                'success' => false,
                'message' => __('menu.table_call.table_not_found'),
            ], 404);
        }

        $hasActive = TableCall::query()
            ->where('table_id', $table->id)
            ->open()
            ->exists();

        if ($hasActive) {
            return response()->json([
                'success' => true,
                'already_active' => true,
                'active' => true,
                'message' => __('menu.table_call.already'),
            ]);
        }

        $type = $validated['type'] ?? TableCall::TYPE_WAITER;

        try {
            $call = TableCall::create([
                'restaurant_id' => $table->restaurant_id,
                'table_id' => $table->id,
                'type' => $type,
                'status' => TableCall::STATUS_PENDING,
            ]);

            if ($call->isBill()) {
                app(DeliveryTaskService::class)->createBillRequestTask($call);
            }

            try {
                event(new TableCallReceived($call));
            } catch (Throwable $e) {
                Log::warning('TableCallReceived broadcast failed; call saved', [
                    'table_call_id' => $call->id,
                    'message' => $e->getMessage(),
                ]);
            }

            app(TableStatusService::class)->markOccupied($table->id);
        } catch (Throwable $e) {
            Log::error('Table call store failed', [
                'table_id' => $table->id,
                'table_token' => $validated['table_token'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Garson çağrısı iletilemedi. Lütfen tekrar deneyin.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'active' => true,
            'message' => __('menu.table_call.waiter'),
        ]);
    }

    /** Müşteri menüsü: aktif çağrı durumu (garson üstlendi mi?). */
    public function status(Request $request): JsonResponse
    {
        MenuLocale::apply($request, MenuLocale::resolve($request));

        $request->validate([
            'table_token' => 'required|string',
        ]);

        $table = $this->resolveTable((string) $request->query('table_token'));
        if (! $table) {
            return response()->json(['active' => false]);
        }

        $call = TableCall::query()
            ->where('table_id', $table->id)
            ->open()
            ->with('waiter:id,name')
            ->first();

        if (! $call) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'type' => $call->type,
            'status' => $call->status,
            'waiter_name' => $call->waiter?->name,
            'message' => $call->customerMessage(),
        ]);
    }

    private function resolveTable(string $tableToken): ?Table
    {
        $query = Table::withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) use ($tableToken) {
                $q->where('uuid', $tableToken)
                    ->orWhere('qr_token', $tableToken);
            });

        $restaurantId = CurrentRestaurant::resolveId();
        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query->first();
    }
}
