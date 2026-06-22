<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use App\Services\Pos\BekoPosClient;
use App\Services\Pos\PosGateway;
use App\Support\CurrentRestaurant;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KasaPanelService
{
    public function __construct(
        private readonly TableStatusService $tableStatus,
        private readonly BekoPosClient $posClient,
        private readonly PosGateway $posGateway,
        private readonly ProductOptionPricing $optionPricing,
    ) {}

    public function ensureCashier(): void
    {
        if (! in_array(session('admin_role'), [User::ROLE_ADMIN, User::ROLE_CASHIER], true)) {
            throw ValidationException::withMessages([
                'auth' => 'Kasa işlemi için yetkiniz yok.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function selectTable(int $tableId, ?int $pinnedOrderId = null): array
    {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);

        return $this->tablePayload($table, $pinnedOrderId);
    }

    /**
     * @param  array<int, array{group_id: int, option_id: int}>  $selectedOptions
     * @return array<string, mixed>
     */
    public function addItem(int $tableId, int $productId, array $selectedOptions = [], ?int $pinnedOrderId = null): array
    {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);
        $product = Product::query()->available()->inStock()->findOrFail($productId);

        $createdNew = false;
        $statusPromoted = false;
        $resolvedOrderId = null;

        DB::transaction(function () use ($table, $product, $selectedOptions, $pinnedOrderId, &$createdNew, &$statusPromoted, &$resolvedOrderId) {
            $order = null;

            if ($pinnedOrderId) {
                $order = Order::query()
                    ->where('table_id', $table->id)
                    ->live()
                    ->whereKey($pinnedOrderId)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $order) {
                $order = Order::query()
                    ->where('table_id', $table->id)
                    ->live()
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $order) {
                $order = $this->createOpenOrderRecord($table->id);
                $createdNew = true;
            }

            $resolvedOrderId = $order->id;
            $previousStatus = $order->status;

            $pricing = $this->optionPricing->resolve($product, $selectedOptions, 'tr');
            $storedOptions = $pricing['options'] !== [] ? $pricing['options'] : null;
            $signature = $this->optionSignature($storedOptions);

            $existing = $order->items()
                ->where('product_id', $product->id)
                ->get()
                ->first(fn ($item) => $this->optionSignature($item->options) === $signature);

            $productName = $product->getTranslation('name', 'tr').$pricing['display_name_suffix'];

            if ($existing) {
                $existing->increment('quantity');
            } else {
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $pricing['unit_price'],
                    'product_name' => $productName,
                    'options' => $storedOptions,
                ]);
            }

            if (in_array($previousStatus, [Order::STATUS_PENDING, Order::STATUS_PENDING_APPROVAL], true)) {
                $order->update(['status' => Order::STATUS_PREPARING]);
                $statusPromoted = true;
            } elseif (
                in_array($previousStatus, [Order::STATUS_READY, Order::STATUS_DELIVERED], true)
                && $order->payment_method === null
            ) {
                $order->update(['status' => Order::STATUS_PREPARING]);
                $statusPromoted = true;
            }

            $this->recalculateOrderTotal($order);
            $this->tableStatus->sync($table->id);
        });

        $table = $table->fresh();
        $payload = $this->tablePayload($table, $resolvedOrderId);

        if ($statusPromoted && ! $createdNew && $payload['order']) {
            $order = Order::query()
                ->with(['items.product:id,type,category_id', 'items.product.category:id,type', 'table:id,number'])
                ->find($payload['order']['id']);

            if ($order) {
                event(OrderStatusUpdated::fromOrder($order));
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateOrderItem(
        int $tableId,
        int $itemId,
        ?int $quantity = null,
        bool $remove = false,
        ?int $pinnedOrderId = null,
    ): array {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);
        $resolvedOrderId = null;

        DB::transaction(function () use ($table, $itemId, $quantity, $remove, $pinnedOrderId, &$resolvedOrderId) {
            $order = null;

            if ($pinnedOrderId) {
                $order = Order::query()
                    ->where('table_id', $table->id)
                    ->live()
                    ->whereKey($pinnedOrderId)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $order) {
                $order = Order::query()
                    ->where('table_id', $table->id)
                    ->live()
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $order || $order->payment_method !== null) {
                throw ValidationException::withMessages([
                    'order' => 'Bu adisyon düzenlenemez.',
                ]);
            }

            $resolvedOrderId = $order->id;

            $item = $order->items()->whereKey($itemId)->first();

            if (! $item) {
                throw ValidationException::withMessages([
                    'item' => 'Ürün satırı bulunamadı.',
                ]);
            }

            if ($remove || ($quantity !== null && $quantity <= 0)) {
                $item->delete();
            } elseif ($quantity !== null) {
                $item->update(['quantity' => $quantity]);
            }

            if (in_array($order->status, [Order::STATUS_READY, Order::STATUS_DELIVERED], true)) {
                $order->update(['status' => Order::STATUS_PREPARING]);
            }

            if ($order->items()->count() === 0) {
                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'total' => '0.00',
                ]);
            } else {
                $this->recalculateOrderTotal($order);
            }

            $this->tableStatus->sync($table->id);
        });

        $payload = $this->tablePayload($table->fresh(), $resolvedOrderId);

        if ($resolvedOrderId) {
            $cancelled = Order::query()->find($resolvedOrderId);
            if ($cancelled && $cancelled->status === Order::STATUS_CANCELLED) {
                $cancelled->load(['items.product:id,type,category_id', 'items.product.category:id,type', 'table:id,number']);
                event(OrderStatusUpdated::fromOrder($cancelled));
            }
        }

        return $payload;
    }

    /**
     * Kasiyer manuel adisyonu garsona bildirir (preparing → ready).
     *
     * @return array<string, mixed>
     */
    public function notifyWaiter(int $tableId, ?int $pinnedOrderId = null): array
    {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);

        $order = null;

        if ($pinnedOrderId) {
            $order = Order::query()
                ->where('table_id', $table->id)
                ->live()
                ->whereKey($pinnedOrderId)
                ->first();
        }

        $order ??= Order::query()
            ->where('table_id', $table->id)
            ->live()
            ->orderByDesc('id')
            ->first();

        if (! $order || $order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'order' => 'Bildirilecek adisyon bulunamadı.',
            ]);
        }

        if ($order->status !== Order::STATUS_PREPARING) {
            throw ValidationException::withMessages([
                'order' => 'Garsona yalnızca hazırlanan adisyonlar bildirilebilir.',
            ]);
        }

        if (! $order->canTransitionTo(Order::STATUS_READY)) {
            throw ValidationException::withMessages([
                'order' => 'Bu adisyon garsona iletilemedi.',
            ]);
        }

        $servedThroughItemId = $order->items()->max('id');
        $taskService = app(DeliveryTaskService::class);

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            if (! in_array($item->preparation_status, [OrderItem::STATUS_READY, OrderItem::STATUS_SERVED, OrderItem::STATUS_CANCELLED], true)) {
                $item->update(['preparation_status' => OrderItem::STATUS_READY]);
            }
            $taskService->ensureDeliveryTaskForItem($item);
        }

        $order->update([
            'status' => Order::STATUS_READY,
            'kasa_served_through_item_id' => $servedThroughItemId ?: $order->kasa_served_through_item_id,
        ]);
        $order->refresh()->load(['items.product:id,type,category_id', 'items.product.category:id,type', 'table:id,number']);
        event(OrderStatusUpdated::fromOrder($order));
        $this->tableStatus->sync($table->id);

        return $this->tablePayload($table->fresh(), $order->id);
    }

    /**
     * Ödeme aşamasındaki adisyonu yeni ürün eklemek için hazırlığa alır (aynı adisyon devam eder).
     *
     * @return array<string, mixed>
     */
    public function resumeOrdering(int $tableId, ?int $pinnedOrderId = null): array
    {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);

        $order = null;

        if ($pinnedOrderId) {
            $order = Order::query()
                ->where('table_id', $table->id)
                ->live()
                ->whereKey($pinnedOrderId)
                ->first();
        }

        $order ??= $this->findOpenOrder($table->id);

        if (
            $order
            && $order->payment_method === null
            && $order->items()->exists()
            && in_array($order->status, [Order::STATUS_READY, Order::STATUS_DELIVERED], true)
        ) {
            $order->update(['status' => Order::STATUS_PREPARING]);
            $order->refresh()->load(['items.product:id,type,category_id', 'items.product.category:id,type', 'table:id,number']);
            event(OrderStatusUpdated::fromOrder($order));
            $this->tableStatus->sync($table->id);
        }

        return $this->tablePayload($table->fresh(), $order?->id ?? $pinnedOrderId);
    }

    /**
     * @return array<string, mixed>
     */
    public function approveOrder(int $tableId): array
    {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);
        $order = $this->findOpenOrder($tableId);

        if (! $order) {
            throw ValidationException::withMessages([
                'order' => 'Onaylanacak açık adisyon bulunamadı.',
            ]);
        }

        if ($order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'order' => 'Adisyona en az bir ürün ekleyin.',
            ]);
        }

        $previousStatus = $order->status;

        if (in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PENDING_APPROVAL], true)) {
            $order->update(['status' => Order::STATUS_PREPARING]);
            $order->refresh()->load(['items.product:id,type,category_id', 'items.product.category:id,type', 'table:id,number']);

            if ($previousStatus === Order::STATUS_PENDING_APPROVAL && $order->table_id) {
                $this->tableStatus->markOccupied($order->table_id);
            }

            if ($previousStatus === Order::STATUS_PENDING) {
                event(new OrderCreated($order, silent: true));
            } else {
                event(OrderStatusUpdated::fromOrder($order));
            }
        }

        return $this->tablePayload($table->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function payWithCash(int $tableId, ?string $idempotencyKey = null): array
    {
        $this->recordManualPayment($tableId, Payment::METHOD_CASH, Payment::MODE_MANUAL_CASH, Order::PAYMENT_CASH, $idempotencyKey);

        return $this->tablePayload($this->findActiveTable($tableId));
    }

    /**
     * @return array<string, mixed>
     */
    public function payWithManualCard(int $tableId, ?string $idempotencyKey = null): array
    {
        $this->recordManualPayment($tableId, Payment::METHOD_CARD, Payment::MODE_MANUAL_CARD, Order::PAYMENT_CARD, $idempotencyKey);

        return $this->tablePayload($this->findActiveTable($tableId));
    }

    /**
     * @return array<string, mixed>
     */
    public function payWithPos(int $tableId, ?string $idempotencyKey = null): array
    {
        $this->ensureCashier();
        $table = $this->findActiveTable($tableId);

        $driver = (string) config('pos.driver', 'disabled');
        if ($driver !== 'fake' || ! app()->environment(['local', 'testing'])) {
            return array_merge($this->tablePayload($table->fresh()), [
                'success' => false,
                'message' => 'POS entegrasyonu henüz aktif değil.',
            ]);
        }

        [$reference, $gatewayResult] = DB::transaction(function () use ($tableId, $idempotencyKey) {
            $order = $this->resolvePayableOrderForUpdate($tableId);

            if ($this->hasActivePosPayment($order)) {
                throw ValidationException::withMessages([
                    'payment' => 'Ödeme işlemi devam ediyor.',
                ]);
            }

            $remaining = $this->remainingAmountForLockedOrder($order);
            $payment = Payment::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'table_id' => $order->table_id,
                'user_id' => $this->currentUserId(),
                'method' => Payment::METHOD_CARD,
                'mode' => Payment::MODE_POS_CARD,
                'provider' => config('pos.driver', 'disabled'),
                'status' => Payment::STATUS_CREATED,
                'amount' => $remaining,
                'currency' => 'TRY',
                'reference' => $this->generatePaymentReference('POS'),
                'idempotency_key' => $idempotencyKey ?: $this->defaultIdempotencyKey($order, Payment::MODE_POS_CARD, $remaining),
                'initiated_at' => now(),
            ]);

            $gatewayResult = $this->posGateway->initiatePayment($order, $payment);
            $payment->refresh();

            if ($payment->isSuccessful()) {
                $this->closeOrderIfFullyPaid($order, Order::PAYMENT_CARD);
            }

            return [$payment->reference, $gatewayResult];
        });


        return array_merge($this->tablePayload($table->fresh()), [
            'success' => (bool) ($gatewayResult['success'] ?? false),
            'pos_reference' => $reference,
            'message' => $gatewayResult['message'] ?? 'POS işlemi başlatıldı.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function completePosPayment(string $reference): array
    {
        $payload = $this->posClient->resolveReference($reference);

        if (! $payload) {
            throw ValidationException::withMessages(['reference' => 'Geçersiz POS referansı.']);
        }

        $table = Table::query()->findOrFail($payload['table_id']);
        $order = Order::query()->findOrFail($payload['order_id']);

        DB::transaction(function () use ($order, $table) {
            if ($order->status !== Order::STATUS_DELIVERED) {
                $order->update(['status' => Order::STATUS_DELIVERED]);
            }

            $order->update(['payment_method' => Order::PAYMENT_CARD]);
            $order->refresh();
            event(OrderStatusUpdated::fromOrder($order));
            $this->tableStatus->sync($table->id);
        });

        return $this->tablePayload($table->fresh());
    }

    private function findActiveTable(int $tableId): Table
    {
        return Table::query()
            ->whereKey($tableId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function createOpenOrderRecord(int $tableId): Order
    {
        $placement = app(OrderPlacementService::class);
        $restaurantId = CurrentRestaurant::resolveId();

        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                return Order::create([
                    'restaurant_id' => $restaurantId,
                    'table_id' => $tableId,
                    'order_number' => $placement->generateOrderNumber(),
                    'status' => Order::STATUS_PREPARING,
                    'source' => Order::SOURCE_KASA,
                    'total' => '0.00',
                ]);
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                    throw $e;
                }

                $raceOrder = Order::query()
                    ->where('table_id', $tableId)
                    ->live()
                    ->orderByDesc('id')
                    ->first();

                if ($raceOrder) {
                    return $raceOrder;
                }

                if ($attempt >= 7) {
                    throw $e;
                }

                usleep(50_000 * ($attempt + 1));
            }
        }

        throw ValidationException::withMessages([
            'order' => 'Yeni adisyon oluşturulamadı. Tekrar deneyin.',
        ]);
    }

    private function findOpenOrder(int $tableId): ?Order
    {
        return Order::query()
            ->where('table_id', $tableId)
            ->live()
            ->orderByDesc('id')
            ->first();
    }

    private function recordManualPayment(
        int $tableId,
        string $method,
        string $mode,
        string $legacyPaymentMethod,
        ?string $idempotencyKey,
    ): void {
        $this->ensureCashier();
        $this->findActiveTable($tableId);

        DB::transaction(function () use ($tableId, $method, $mode, $legacyPaymentMethod, $idempotencyKey) {
            $order = $this->resolvePayableOrderForUpdate($tableId);
            $remaining = $this->remainingAmountForLockedOrder($order);
            $key = $idempotencyKey ?: $this->defaultIdempotencyKey($order, $mode, $remaining);

            $existing = Payment::query()
                ->where('order_id', $order->id)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                Payment::create([
                    'restaurant_id' => $order->restaurant_id,
                    'order_id' => $order->id,
                    'table_id' => $order->table_id,
                    'user_id' => $this->currentUserId(),
                    'method' => $method,
                    'mode' => $mode,
                    'provider' => 'manual',
                    'status' => Payment::STATUS_SUCCESS,
                    'amount' => $remaining,
                    'currency' => 'TRY',
                    'reference' => $this->generatePaymentReference($method === Payment::METHOD_CASH ? 'CASH' : 'CARD'),
                    'idempotency_key' => $key,
                    'completed_at' => now(),
                ]);
            }

            $this->closeOrderIfFullyPaid($order, $legacyPaymentMethod);
        });
    }

    private function resolvePayableOrderForUpdate(int $tableId): Order
    {
        $order = Order::query()
            ->where('table_id', $tableId)
            ->whereIn('status', [
                Order::STATUS_PENDING,
                Order::STATUS_PENDING_APPROVAL,
                Order::STATUS_PREPARING,
                Order::STATUS_READY,
                Order::STATUS_DELIVERED,
            ])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $order || $order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'order' => 'Bu masada ödenecek adisyon bulunamadı.',
            ]);
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'order' => 'İptal edilmiş adisyon ödenemez.',
            ]);
        }

        if ($order->isFullyPaid()) {
            throw ValidationException::withMessages([
                'payment' => 'Sipariş zaten ödenmiş.',
            ]);
        }

        return $order;
    }

    private function remainingAmountForLockedOrder(Order $order): string
    {
        $remaining = $order->remainingAmount();

        if (bccomp($remaining, '0.00', Money::SCALE) <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'Sipariş zaten ödenmiş.',
            ]);
        }

        return $remaining;
    }

    private function closeOrderIfFullyPaid(Order $order, string $legacyPaymentMethod): void
    {
        $order->refresh();

        if (! $order->isFullyPaid()) {
            return;
        }

        $payload = ['payment_method' => $legacyPaymentMethod];

        if ($order->status !== Order::STATUS_DELIVERED) {
            $payload['status'] = Order::STATUS_DELIVERED;
        }

        $order->update($payload);
        $order->refresh();
        event(OrderStatusUpdated::fromOrder($order));
        $this->tableStatus->sync($order->table_id);
    }

    private function hasActivePosPayment(Order $order): bool
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('mode', Payment::MODE_POS_CARD)
            ->whereIn('status', [
                Payment::STATUS_CREATED,
                Payment::STATUS_PENDING,
                Payment::STATUS_PROCESSING,
            ])
            ->exists();
    }

    private function currentUserId(): ?int
    {
        $id = session('admin_user_id');

        return $id ? (int) $id : null;
    }

    private function defaultIdempotencyKey(Order $order, string $mode, string $amount): string
    {
        return 'kasa:'.$mode.':order:'.$order->id.':amount:'.Money::normalize($amount);
    }

    private function generatePaymentReference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.Str::upper(Str::random(16));
        } while (Payment::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    private function resolvePayableOrder(int $tableId): Order
    {
        $order = Order::query()
            ->where('table_id', $tableId)
            ->where(function ($q) {
                $q->whereNull('payment_method')
                    ->whereIn('status', [
                        Order::STATUS_PENDING,
                        Order::STATUS_PENDING_APPROVAL,
                        Order::STATUS_PREPARING,
                        Order::STATUS_READY,
                        Order::STATUS_DELIVERED,
                    ]);
            })
            ->orderByDesc('id')
            ->first();

        if (! $order || $order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'order' => 'Bu masada ödenecek adisyon bulunamadı.',
            ]);
        }

        return $order;
    }

    private function recalculateOrderTotal(Order $order): void
    {
        $order->load('items');
        $total = '0.00';

        foreach ($order->items as $item) {
            $total = Money::add($total, Money::mul($item->unit_price, $item->quantity));
        }

        $order->update(['total' => Money::normalize($total)]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $options
     */
    private function optionSignature(?array $options): string
    {
        if ($options === null || $options === []) {
            return '';
        }

        return collect($options)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->join(',');
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePayload(Table $table, ?int $pinnedOrderId = null): array
    {
        $orderQuery = Order::query()
            ->where('table_id', $table->id)
            ->live()
            ->with([
                'items:id,order_id,product_id,product_name,quantity,unit_price,notes,preparation_status,options',
                'items.product:id,type,station,category_id',
                'items.product.category:id,type',
            ]);

        if ($pinnedOrderId) {
            $order = (clone $orderQuery)->whereKey($pinnedOrderId)->first();
        } else {
            $order = null;
        }

        $order ??= (clone $orderQuery)->orderByDesc('id')->first();

        $items = $order?->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->product_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'notes' => $item->notes,
            'options' => $item->options ?? [],
            'type' => $item->product?->stationType() ?? 'kitchen',
            'station' => $item->product?->stationType() ?? 'kitchen',
            'preparation_status' => $item->preparation_status ?? 'waiting',
        ])->values()->all() ?? [];

        $canApprove = $order
            && $order->items->isNotEmpty()
            && $order->status === Order::STATUS_PENDING_APPROVAL;

        $canPay = $order
            && $order->items->isNotEmpty()
            && $order->payment_method === null
            && $table->status !== Table::STATUS_PAYMENT_PROCESSING;

        return [
            'table' => [
                'id' => $table->id,
                'number' => $table->number,
                'status' => $table->status,
            ],
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $order->status_label,
                'source' => $order->source,
                'source_label' => $order->source_label,
                'is_waiter_order' => $order->isWaiterOrder(),
                'total' => $order->total,
                'payment_method' => $order->payment_method,
                'table' => $table->number,
                'kasa_served_through_item_id' => $order->kasa_served_through_item_id,
                'items' => $items,
            ] : null,
            'can_approve' => $canApprove,
            'can_pay' => $canPay,
        ];
    }
}
