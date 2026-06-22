<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Services\TableStatusService;
use App\Support\CurrentRestaurant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderPlacementService
{
    public function __construct(
        private readonly ProductOptionPricing $optionPricing,
    ) {}
    public function generateOrderNumber(): string
    {
        $prefix = 'H'.date('ymd');
        $restaurantId = CurrentRestaurant::resolveId();

        $query = Order::query()
            ->where('order_number', 'like', $prefix.'%');

        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        $latest = $query->orderByDesc('id')->value('order_number');

        $seq = 1;
        if (is_string($latest) && str_starts_with($latest, $prefix)) {
            $seq = max(1, (int) substr($latest, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, notes?: string|null, options?: array<int, array{group_id: int, option_id: int}>}>  $items
     */
    public function createOrder(
        ?int $tableId,
        array $items,
        string $source = Order::SOURCE_QR,
        ?string $notes = null,
    ): Order {
        if ($tableId !== null && ! Table::whereKey($tableId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'table_id' => 'Geçerli ve aktif bir masa seçin.',
            ]);
        }

        $tableStatus = app(TableStatusService::class);

        return DB::transaction(function () use ($tableId, $items, $source, $notes, $tableStatus) {
            $restaurantId = CurrentRestaurant::resolveId();

            if ($restaurantId === null) {
                throw ValidationException::withMessages([
                    'restaurant' => 'Sipariş için restoran bağlamı bulunamadı.',
                ]);
            }

            $initialStatus = $source === Order::SOURCE_QR
                ? Order::STATUS_PENDING_APPROVAL
                : Order::STATUS_PREPARING;

            $order = Order::create([
                'restaurant_id' => $restaurantId,
                'table_id' => $tableId,
                'order_number' => $this->generateOrderNumber(),
                'status' => $initialStatus,
                'source' => $source,
                'notes' => $notes,
                'total' => '0.00',
            ]);

            $total = '0.00';
            $added = 0;

            foreach ($items as $item) {
                $product = Product::query()->find($item['product_id'] ?? null);
                if (! $product || ! $product->is_available || ! $product->in_stock) {
                    continue;
                }

                $qty = (int) ($item['quantity'] ?? 1);
                $locale = app()->getLocale();
                $pricing = $this->optionPricing->resolve(
                    $product,
                    $item['options'] ?? [],
                    $locale,
                );

                $productName = $product->getTranslation('name', $locale, false)
                    ?: $product->getTranslation('name', 'tr');
                $productName .= $pricing['display_name_suffix'];

                // Güncel fiyat snapshot — sonradan ürün fiyatı değişse geçmiş sipariş cirosu bozulmaz.
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $pricing['unit_price'],
                    'product_name' => $productName,
                    'notes' => $item['notes'] ?? null,
                    'options' => $pricing['options'] !== [] ? $pricing['options'] : null,
                ]);
                $total = Money::add($total, Money::mul($pricing['unit_price'], $qty));
                $added++;
            }

            if ($added === 0) {
                throw ValidationException::withMessages([
                    'items' => 'En az bir müsait ürün ekleyin.',
                ]);
            }

            $order->update(['total' => Money::normalize($total)]);

            $order = $order->load([
                'items.product:id,type,category_id',
                'items.product.category:id,type',
                'table:id,number',
            ]);

            if ($tableId !== null && $initialStatus !== Order::STATUS_PENDING_APPROVAL) {
                $tableStatus->markOccupied($tableId);
            }

            try {
                event(new OrderCreated($order, silent: in_array($order->source, [Order::SOURCE_WAITER, Order::SOURCE_KASA], true)));
            } catch (\Throwable $e) {
                Log::warning('OrderCreated broadcast failed; order saved', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return $order;
        });
    }
}
