<?php

namespace App\Http\Controllers\Admin;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Setting;
use App\Models\Table;
use App\Services\OrderPlacementService;
use App\Services\TableStatusService;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualOrderController extends Controller
{
    private function ensureStaffAccess(): ?\Illuminate\Http\JsonResponse
    {
        if (! in_array(session('admin_role'), [User::ROLE_ADMIN, User::ROLE_CASHIER, User::ROLE_WAITER], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Sipariş alma için yetkiniz yok.',
            ], 403);
        }

        return null;
    }

    public function bootstrap(): \Illuminate\Http\JsonResponse
    {
        if ($forbidden = $this->ensureStaffAccess()) {
            return $forbidden;
        }

        $tables = Table::query()
            ->where('is_active', true)
            ->orderByNumber()
            ->get(['id', 'number'])
            ->map(fn (Table $t) => ['id' => $t->id, 'number' => $t->number]);

        $categories = Category::query()
            ->active()
            ->get(['id', 'name', 'slug', 'type', 'icon'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->getTranslation('name', 'tr'),
                'slug' => $c->slug,
                'type' => $c->type,
                'icon' => $c->icon,
            ]);

        $settings = Setting::allCached();

        return response()->json([
            'tables' => $tables,
            'categories' => $categories,
            'currency' => $settings['currency'] ?? '₺',
        ]);
    }

    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($forbidden = $this->ensureStaffAccess()) {
            return $forbidden;
        }

        $q = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $productId = $request->query('product_id');
        $loadOptions = filled($productId);

        $products = Product::query()
            ->available()
            ->inStock()
            ->when($loadOptions, fn ($query) => $query->with([
                'category:id,name',
                'optionGroups' => fn ($q) => $q->orderBy('sort_order')->with('options'),
            ]))
            ->when(! $loadOptions, fn ($query) => $query
                ->with('category:id,name')
                ->withCount([
                    'optionGroups as configurable_options_count' => fn ($q) => $q->whereHas('options'),
                ]))
            ->when(filled($productId), fn ($query) => $query->whereKey((int) $productId))
            ->when(filled($categoryId), fn ($query) => $query->where('category_id', (int) $categoryId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name->tr', 'like', "%{$q}%")
                        ->orWhere('name->en', 'like', "%{$q}%")
                        ->orWhere('name->ru', 'like', "%{$q}%")
                        ->orWhereHas('category', function ($c) use ($q) {
                            $c->where('name->tr', 'like', "%{$q}%")
                                ->orWhere('name->en', 'like', "%{$q}%")
                                ->orWhere('name->ru', 'like', "%{$q}%");
                        });
                });
            })
            ->limit($loadOptions ? 1 : (filled($categoryId) ? 100 : 48))
            ->get(['id', 'name', 'price', 'type', 'category_id']);

        return response()->json([
            'products' => $products->map(function (Product $p) use ($loadOptions) {
                if ($loadOptions) {
                    $groups = collect($p->cartOptionsPayload('tr'))
                        ->filter(fn (array $group) => count($group['options'] ?? []) > 0)
                        ->values()
                        ->all();

                    return [
                        'id' => $p->id,
                        'name' => $p->getTranslation('name', 'tr'),
                        'price' => $p->price,
                        'type' => $p->type ?? 'kitchen',
                        'category' => $p->category?->getTranslation('name', 'tr'),
                        'has_options' => count($groups) > 0,
                        'option_groups' => $groups,
                    ];
                }

                return [
                    'id' => $p->id,
                    'name' => $p->getTranslation('name', 'tr'),
                    'price' => $p->price,
                    'type' => $p->type ?? 'kitchen',
                    'category' => $p->category?->getTranslation('name', 'tr'),
                    'has_options' => (int) ($p->configurable_options_count ?? 0) > 0,
                    'option_groups' => [],
                ];
            }),
        ]);
    }

    public function store(Request $request, OrderPlacementService $placement): \Illuminate\Http\JsonResponse
    {
        if ($forbidden = $this->ensureStaffAccess()) {
            return $forbidden;
        }

        $validated = $request->validate([
            'table_id' => ['required', TenantRules::existsModel(Table::class)],
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', TenantRules::existsModel(Product::class)],
            'items.*.quantity' => 'required|integer|min:1|max:20',
            'items.*.options' => 'nullable|array',
            'items.*.options.*.group_id' => 'required_with:items.*.options|integer',
            'items.*.options.*.option_id' => 'required_with:items.*.options|integer',
        ]);

        $order = $placement->createOrder(
            (int) $validated['table_id'],
            $validated['items'],
            Order::SOURCE_WAITER,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => "Sipariş #{$order->order_number} mutfağa iletildi.",
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'table' => $order->table?->number,
                'source' => $order->source,
            ],
        ], 201);
    }

    public function activeTableOrder(Table $table): \Illuminate\Http\JsonResponse
    {
        if ($forbidden = $this->ensureStaffAccess()) {
            return $forbidden;
        }

        $order = Order::query()
            ->where('table_id', $table->id)
            ->live()
            ->where('source', Order::SOURCE_WAITER)
            ->whereIn('status', [Order::STATUS_PREPARING, Order::STATUS_PENDING_APPROVAL])
            ->orderByDesc('id')
            ->with(['items:id,order_id,product_name,quantity,unit_price'])
            ->first();

        if (! $order) {
            return response()->json(['order' => null]);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'total' => $order->total,
                'can_cancel' => $order->canTransitionTo(Order::STATUS_CANCELLED),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->values(),
            ],
        ]);
    }

    public function cancelOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        if ($forbidden = $this->ensureStaffAccess()) {
            return $forbidden;
        }

        if (! $order->isWaiterOrder()) {
            return response()->json([
                'success' => false,
                'message' => 'Yalnızca garson siparişleri iptal edilebilir.',
            ], 422);
        }

        if (! in_array($order->status, [Order::STATUS_PREPARING, Order::STATUS_PENDING_APPROVAL], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Yalnızca mutfağa yeni giden siparişler iptal edilebilir.',
            ], 422);
        }

        if (! $order->canTransitionTo(Order::STATUS_CANCELLED)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu sipariş artık iptal edilemez.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => Order::STATUS_CANCELLED]);
            $order->refresh()->load(['items.product:id,type,category_id', 'items.product.category:id,type', 'table:id,number']);
            event(OrderStatusUpdated::fromOrder($order));

            if ($order->table_id) {
                app(TableStatusService::class)->sync($order->table_id);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Sipariş iptal edildi. Sepeti yeniden oluşturabilirsiniz.',
        ]);
    }
}
