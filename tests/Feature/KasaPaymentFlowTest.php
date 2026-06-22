<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use App\Services\TableStatusService;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class KasaPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CurrentRestaurant::clear();

        parent::tearDown();
    }

    public function test_cash_payment_creates_payment_record(): void
    {
        [$restaurant, $order, $table, $user] = $this->paymentContext('100.00');

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'table_id' => $table->id,
            'user_id' => $user->id,
            'method' => Payment::METHOD_CASH,
            'mode' => Payment::MODE_MANUAL_CASH,
            'provider' => 'manual',
            'status' => Payment::STATUS_SUCCESS,
            'amount' => '100.00',
        ]);
    }

    public function test_manual_card_payment_creates_payment_record(): void
    {
        [$restaurant, $order, $table, $user] = $this->paymentContext('120.00');

        $this->asStaff($user)->postJson(route('admin.kasa.pay-card'), [
            'table_id' => $table->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'method' => Payment::METHOD_CARD,
            'mode' => Payment::MODE_MANUAL_CARD,
            'provider' => 'manual',
            'status' => Payment::STATUS_SUCCESS,
            'amount' => '120.00',
        ]);
        $this->assertSame(Order::PAYMENT_CARD, $order->fresh()->payment_method);
    }

    public function test_order_total_cannot_be_changed_from_request_amount(): void
    {
        [, $order, $table, $user] = $this->paymentContext('75.50');

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
            'amount' => '0.01',
        ])->assertOk();

        $this->assertSame('75.50', $order->fresh()->payments()->first()->amount);
    }

    public function test_full_payment_closes_order(): void
    {
        [, $order, $table, $user] = $this->paymentContext('90.00', Order::STATUS_READY);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertSame(Order::PAYMENT_CASH, $order->payment_method);
        $this->assertTrue($order->isFullyPaid());
    }

    public function test_partial_payment_does_not_close_order(): void
    {
        [, $order] = $this->paymentContext('100.00', Order::STATUS_DELIVERED);

        Payment::factory()->successfulCash()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'table_id' => $order->table_id,
            'amount' => '40.00',
        ]);

        $order->refresh();
        $this->assertFalse($order->isFullyPaid());
        $this->assertNull($order->payment_method);
    }

    public function test_full_payment_frees_table(): void
    {
        [, , $table, $user] = $this->paymentContext('100.00', Order::STATUS_DELIVERED);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertOk();

        $this->assertSame(Table::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_partial_payment_does_not_free_table(): void
    {
        [, $order, $table] = $this->paymentContext('100.00', Order::STATUS_DELIVERED);

        Payment::factory()->successfulCash()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'table_id' => $table->id,
            'amount' => '40.00',
        ]);

        app(TableStatusService::class)->sync($table->id);

        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_table_stays_occupied_before_payment_is_completed(): void
    {
        [, $order, $table] = $this->paymentContext('100.00', Order::STATUS_DELIVERED);

        Payment::factory()->pendingPos()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'table_id' => $table->id,
            'amount' => '100.00',
        ]);

        app(TableStatusService::class)->sync($table->id);

        $this->assertNull($order->fresh()->payment_method);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_paid_order_cannot_be_paid_again(): void
    {
        [, , $table, $user] = $this->paymentContext('100.00');

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
            'idempotency_key' => 'same-click',
        ])->assertOk();

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
            'idempotency_key' => 'same-click',
        ])->assertStatus(422);

        $this->assertSame(1, Payment::withoutGlobalScopes()->count());
    }

    public function test_cancelled_order_cannot_be_paid(): void
    {
        [, , $table, $user] = $this->paymentContext('100.00', Order::STATUS_CANCELLED);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertStatus(422);
    }

    public function test_waiter_cannot_create_manual_card_payment(): void
    {
        [$restaurant, , $table] = $this->paymentContext('100.00');
        $waiter = $this->staffUser($restaurant, User::ROLE_WAITER);

        $this->asStaff($waiter)->postJson(route('admin.kasa.pay-card'), [
            'table_id' => $table->id,
        ])->assertStatus(422);
    }

    public function test_waiter_cannot_create_cash_payment(): void
    {
        [$restaurant, , $table] = $this->paymentContext('100.00');
        $waiter = $this->staffUser($restaurant, User::ROLE_WAITER);

        $this->asStaff($waiter)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertStatus(422);
    }

    public function test_admin_can_create_manual_card_payment(): void
    {
        [, , $table, $user] = $this->paymentContext('100.00', role: User::ROLE_ADMIN);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-card'), [
            'table_id' => $table->id,
        ])->assertOk();
    }

    public function test_cashier_can_create_manual_card_payment(): void
    {
        [, , $table, $user] = $this->paymentContext('100.00', role: User::ROLE_CASHIER);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-card'), [
            'table_id' => $table->id,
        ])->assertOk();
    }

    public function test_disabled_pos_does_not_close_order_or_create_success_payment(): void
    {
        config()->set('pos.driver', 'disabled');
        [, $order, $table, $user] = $this->paymentContext('100.00');

        $this->asStaff($user)->postJson(route('admin.kasa.pay-pos'), [
            'table_id' => $table->id,
        ])->assertOk()->assertJson(['success' => false]);

        $this->assertNull($order->fresh()->payment_method);
        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
        $this->assertSame(Table::STATUS_OCCUPIED, $table->fresh()->status);
        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
        $this->assertSame(0, Payment::successful()->count());
    }

    public function test_duplicate_click_does_not_create_double_payment(): void
    {
        [, , $table, $user] = $this->paymentContext('100.00');

        $payload = ['table_id' => $table->id, 'idempotency_key' => 'duplicate-click'];
        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), $payload)->assertOk();
        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), $payload)->assertStatus(422);

        $this->assertSame(1, Payment::withoutGlobalScopes()->count());
    }

    public function test_successful_payments_cannot_exceed_order_total(): void
    {
        [, $order, $table, $user] = $this->paymentContext('100.00');
        Payment::factory()->successfulCash()->create([
            'restaurant_id' => $order->restaurant_id,
            'order_id' => $order->id,
            'table_id' => $table->id,
            'amount' => '60.00',
        ]);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertOk();

        $this->assertSame('100.00', $order->fresh()->paidAmount());
    }

    public function test_order_from_another_tenant_cannot_be_paid(): void
    {
        [, , $foreignTable] = $this->paymentContext('100.00');
        $restaurant = Restaurant::create(['name' => 'Other Tenant', 'slug' => 'other-'.Str::lower(Str::random(6))]);
        $user = $this->staffUser($restaurant, User::ROLE_CASHIER);

        $this->asStaff($user)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $foreignTable->id,
        ])->assertStatus(404);
    }

    /**
     * @return array{Restaurant, Order, Table, User}
     */
    private function paymentContext(string $total, string $status = Order::STATUS_DELIVERED, string $role = User::ROLE_CASHIER): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Payment Tenant',
            'slug' => 'payment-'.Str::lower(Str::random(6)),
        ]);
        CurrentRestaurant::set($restaurant);

        $user = $this->staffUser($restaurant, $role);

        $table = Table::create([
            'restaurant_id' => $restaurant->id,
            'number' => (string) random_int(100, 999),
            'qr_token' => Str::random(16),
            'is_active' => true,
            'status' => Table::STATUS_OCCUPIED,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => ['tr' => 'Test'],
            'slug' => 'test-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => ['tr' => 'Product'],
            'price' => $total,
            'is_available' => true,
            'in_stock' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'order_number' => 'P'.Str::upper(Str::random(10)),
            'status' => $status,
            'total' => $total,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $total,
            'product_name' => 'Product',
        ]);

        return [$restaurant, $order, $table, $user];
    }

    private function staffUser(Restaurant $restaurant, string $role): User
    {
        return User::create([
            'restaurant_id' => $restaurant->id,
            'name' => $role.' User',
            'email' => $role.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function asStaff(User $user): self
    {
        CurrentRestaurant::set($user->restaurant);

        return $this->withSession([
            'admin_logged_in' => true,
            'admin_user_id' => $user->id,
            'admin_name' => $user->name,
            'admin_role' => $user->role,
            'admin_restaurant_id' => $user->restaurant_id,
        ]);
    }
}
