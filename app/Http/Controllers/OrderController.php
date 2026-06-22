<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Services\OrderPlacementService;
use App\Support\CurrentRestaurant;
use App\Support\MenuLocale;
use App\Support\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    private const FINAL_STATUSES = ['cancelled', 'completed'];

    public function store(Request $request, OrderPlacementService $placement): JsonResponse
    {
        $locale = MenuLocale::resolve($request);
        MenuLocale::apply($request, $locale);

        $validated = $request->validate([
            'table_token' => 'nullable|string',
            'lang' => 'nullable|string|in:tr,en,ru',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', TenantRules::existsModel(Product::class)],
            'items.*.quantity' => 'required|integer|min:1|max:20',
            'items.*.notes' => 'nullable|string|max:200',
            'items.*.options' => 'nullable|array',
            'items.*.options.*.group_id' => 'required_with:items.*.options|integer',
            'items.*.options.*.option_id' => 'required_with:items.*.options|integer',
        ]);

        $tableId = $this->resolveTableId($validated['table_token'] ?? null);

        try {
            $order = $placement->createOrder(
                $tableId,
                $validated['items'],
                Order::SOURCE_QR,
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('QR order store failed', [
                'table_id' => $tableId,
                'restaurant_id' => CurrentRestaurant::resolveId(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sipariş kaydedilemedi. Lütfen tekrar deneyin.',
            ], 500);
        }

        $redirect = route('order.status', ['orderToken' => $order->public_token]).'?lang='.$locale;

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'redirect' => $redirect,
        ]);
    }

    private function resolveTableId(?string $tableToken): ?int
    {
        if ($tableToken === null || $tableToken === '') {
            return null;
        }

        $query = Table::query()
            ->where(function ($q) use ($tableToken) {
                $q->where('uuid', $tableToken)
                    ->orWhere('qr_token', $tableToken);
            });

        $restaurantId = CurrentRestaurant::resolveId();
        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query->value('id');
    }

    public function status(Request $request, string $orderToken): View
    {
        $locale = MenuLocale::resolve($request);
        MenuLocale::apply($request, $locale);

        $order = $this->findPublicOrder($orderToken);

        $settings = \App\Models\Setting::allCached();

        return view('menu.status', compact('order', 'settings', 'locale'));
    }

    public function statusApi(Request $request, string $orderToken): JsonResponse
    {
        MenuLocale::apply($request, MenuLocale::resolve($request));

        $order = $this->findPublicOrder($orderToken);

        return response()->json([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $order->status_label,
            'customer_status_label' => $order->customer_status_label,
            'customer_status_message' => $order->customer_status_message,
            'status_step' => $order->customerStatusStep(),
            'payment_method' => $order->payment_method,
            'payment_method_label' => $order->payment_method_label,
            'total' => $order->total,
            'table' => $order->table?->number,
            'updated_at' => $order->updated_at->toIso8601String(),
            'is_final' => in_array($order->status, self::FINAL_STATUSES, true)
                || $order->isClosed(),
        ]);
    }

    private function findPublicOrder(string $orderToken): Order
    {
        $order = Order::withoutGlobalScopes()
            ->where('public_token', $orderToken)
            ->with([
                'restaurant',
                'items:id,order_id,product_name,quantity,unit_price',
                'table:id,number,qr_token,restaurant_id',
            ])
            ->firstOrFail();

        if ($order->restaurant?->is_active) {
            CurrentRestaurant::set($order->restaurant);
        }

        return $order;
    }
}
