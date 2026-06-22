<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\Table;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CurrentRestaurant::clear();

        parent::tearDown();
    }

    public function test_payment_can_be_created(): void
    {
        [$restaurant, $order, $table] = $this->createOrderContext('120.00');

        $payment = Payment::factory()->successfulCash()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'table_id' => $table->id,
            'amount' => '120.00',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'method' => Payment::METHOD_CASH,
            'mode' => Payment::MODE_MANUAL_CASH,
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    public function test_payment_has_order_and_restaurant_relationships(): void
    {
        [$restaurant, $order] = $this->createOrderContext('80.00');

        $payment = Payment::factory()->successfulManualCard()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'amount' => '80.00',
        ]);

        $this->assertTrue($payment->order->is($order));
        $this->assertTrue($payment->restaurant->is($restaurant));
    }

    public function test_successful_payment_scope_works(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '60.00'));
        Payment::factory()->failedPos()->create($this->paymentContext($restaurant, $order, '40.00'));

        $this->assertSame(1, Payment::successful()->count());
    }

    public function test_active_payment_scope_works(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->pendingPos()->create($this->paymentContext($restaurant, $order, '100.00'));
        Payment::factory()->unknownPos()->create($this->paymentContext($restaurant, $order, '100.00'));
        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '100.00'));

        $this->assertSame(2, Payment::active()->count());
    }

    public function test_order_paid_amount_is_calculated_from_successful_payments(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '40.25'));
        Payment::factory()->successfulManualCard()->create($this->paymentContext($restaurant, $order, '9.75'));

        $this->assertSame('50.00', $order->paidAmount());
    }

    public function test_order_remaining_amount_is_calculated(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '33.30'));

        $this->assertSame('66.70', $order->remainingAmount());
    }

    public function test_fully_paid_order_returns_true(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '60.00'));
        Payment::factory()->successfulManualCard()->create($this->paymentContext($restaurant, $order, '40.00'));

        $this->assertTrue($order->isFullyPaid());
    }

    public function test_partially_paid_order_returns_false(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '99.99'));

        $this->assertFalse($order->isFullyPaid());
    }

    public function test_failed_and_cancelled_payments_are_not_counted_in_paid_amount(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '25.00'));
        Payment::factory()->failedPos()->create($this->paymentContext($restaurant, $order, '25.00'));
        Payment::factory()->create(array_merge(
            $this->paymentContext($restaurant, $order, '25.00'),
            ['status' => Payment::STATUS_CANCELLED],
        ));

        $this->assertSame('25.00', $order->paidAmount());
    }

    public function test_order_can_have_multiple_payments(): void
    {
        [$restaurant, $order] = $this->createOrderContext('100.00');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '50.00'));
        Payment::factory()->successfulManualCard()->create($this->paymentContext($restaurant, $order, '50.00'));

        $this->assertSame(2, $order->payments()->count());
    }

    public function test_payment_tenant_global_scope_keeps_existing_isolation_behavior(): void
    {
        [$firstRestaurant, $firstOrder] = $this->createOrderContext('50.00', 'first');
        Payment::factory()->successfulCash()->create($this->paymentContext($firstRestaurant, $firstOrder, '50.00'));

        [$secondRestaurant, $secondOrder] = $this->createOrderContext('50.00', 'second');
        Payment::factory()->successfulCash()->create($this->paymentContext($secondRestaurant, $secondOrder, '50.00'));

        CurrentRestaurant::set($firstRestaurant);
        $this->assertSame(1, Payment::count());

        CurrentRestaurant::set($secondRestaurant);
        $this->assertSame(1, Payment::count());
    }

    public function test_decimal_amounts_do_not_drift(): void
    {
        [$restaurant, $order] = $this->createOrderContext('0.30');

        Payment::factory()->successfulCash()->create($this->paymentContext($restaurant, $order, '0.10'));
        Payment::factory()->successfulManualCard()->create($this->paymentContext($restaurant, $order, '0.20'));

        $this->assertSame('0.30', $order->paidAmount());
        $this->assertSame('0.00', $order->remainingAmount());
        $this->assertTrue($order->isFullyPaid());
    }

    /**
     * @return array{Restaurant, Order, Table}
     */
    private function createOrderContext(string $total, string $suffix = 'main'): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Restaurant '.$suffix,
            'slug' => 'restaurant-'.$suffix.'-'.Str::lower(Str::random(6)),
        ]);

        CurrentRestaurant::set($restaurant);

        $table = Table::create([
            'restaurant_id' => $restaurant->id,
            'number' => (string) random_int(100, 999),
            'qr_token' => Str::random(16),
            'is_active' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'order_number' => 'T'.Str::upper(Str::random(10)),
            'status' => Order::STATUS_DELIVERED,
            'total' => $total,
        ]);

        return [$restaurant, $order, $table];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentContext(Restaurant $restaurant, Order $order, string $amount): array
    {
        return [
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'table_id' => $order->table_id,
            'amount' => $amount,
        ];
    }
}
