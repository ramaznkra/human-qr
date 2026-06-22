<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryTaskFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CurrentRestaurant::clear();

        parent::tearDown();
    }

    public function test_ready_item_creates_single_delivery_task(): void
    {
        [, , , $item, $cashier] = $this->operationContext();

        $payload = ['item_id' => $item->id, 'status' => OrderItem::STATUS_READY];

        $this->asStaff($cashier)->postJson(route('admin.kasa.item-status'), $payload)->assertOk();
        $this->asStaff($cashier)->postJson(route('admin.kasa.item-status'), $payload)->assertOk();

        $this->assertSame(1, DeliveryTask::query()
            ->where('order_item_id', $item->id)
            ->where('type', DeliveryTask::TYPE_DELIVER_ITEM)
            ->count());
    }

    public function test_assigned_waiter_sees_assigned_task(): void
    {
        [, , $table, $item, $cashier, $waiter] = $this->operationContext(assignWaiter: true);

        $this->asStaff($cashier)->postJson(route('admin.kasa.item-status'), [
            'item_id' => $item->id,
            'status' => OrderItem::STATUS_READY,
        ])->assertOk();

        $this->asStaff($waiter)->getJson(route('live-orders.api'))
            ->assertOk()
            ->assertJsonPath('delivery_tasks.0.table_id', $table->id)
            ->assertJsonPath('delivery_tasks.0.assigned_user_id', $waiter->id);
    }

    public function test_unassigned_task_falls_to_common_pool(): void
    {
        [, , , $item, $cashier] = $this->operationContext();

        $this->asStaff($cashier)->postJson(route('admin.kasa.item-status'), [
            'item_id' => $item->id,
            'status' => OrderItem::STATUS_READY,
        ])->assertOk();

        $task = DeliveryTask::query()->firstOrFail();

        $this->assertNull($task->assigned_user_id);
        $this->assertSame(DeliveryTask::STATUS_PENDING, $task->status);
    }

    public function test_waiter_can_accept_and_complete_task(): void
    {
        [, $order, , $item, $cashier, $waiter] = $this->operationContext();

        $this->asStaff($cashier)->postJson(route('admin.kasa.item-status'), [
            'item_id' => $item->id,
            'status' => OrderItem::STATUS_READY,
        ])->assertOk();

        $task = DeliveryTask::query()->firstOrFail();

        $this->asStaff($waiter)->patchJson(route('waiter.tasks.accept', $task))->assertOk();
        $this->assertSame(DeliveryTask::STATUS_ACCEPTED, $task->fresh()->status);
        $this->assertSame($waiter->id, $task->fresh()->assigned_user_id);

        $this->asStaff($waiter)->patchJson(route('waiter.tasks.complete', $task))->assertOk();
        $this->assertSame(DeliveryTask::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame(OrderItem::STATUS_SERVED, $item->fresh()->preparation_status);
        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_method);
    }

    public function test_waiter_cannot_take_payment(): void
    {
        [, , $table, , , $waiter] = $this->operationContext();

        $this->asStaff($waiter)->postJson(route('admin.kasa.pay-cash'), [
            'table_id' => $table->id,
        ])->assertStatus(422);
    }

    public function test_bill_request_creates_task_but_does_not_mark_paid(): void
    {
        [, $order, $table] = $this->operationContext(status: Order::STATUS_DELIVERED);

        $this->postJson(route('table.call.api'), [
            'table_token' => $table->uuid,
            'type' => 'bill',
        ])->assertOk();

        $this->assertDatabaseHas('delivery_tasks', [
            'order_id' => $order->id,
            'table_id' => $table->id,
            'type' => DeliveryTask::TYPE_BILL_REQUEST,
            'status' => DeliveryTask::STATUS_PENDING,
        ]);
        $this->assertNull($order->fresh()->payment_method);
    }

    public function test_order_cannot_be_closed_before_all_items_are_served(): void
    {
        [, $order, , , , $waiter] = $this->operationContext(status: Order::STATUS_READY);

        $this->asStaff($waiter)->postJson(route('waiter.complete'), [
            'type' => 'order',
            'id' => $order->id,
        ])->assertStatus(422);

        $this->assertSame(Order::STATUS_READY, $order->fresh()->status);
        $this->assertNull($order->fresh()->payment_method);
    }

    public function test_task_from_another_tenant_is_not_visible(): void
    {
        [, , , $item, $cashier] = $this->operationContext();

        $this->asStaff($cashier)->postJson(route('admin.kasa.item-status'), [
            'item_id' => $item->id,
            'status' => OrderItem::STATUS_READY,
        ])->assertOk();

        $other = Restaurant::create(['name' => 'Other Tenant', 'slug' => 'other-'.Str::lower(Str::random(6))]);
        $otherWaiter = $this->staffUser($other, User::ROLE_WAITER);

        $this->asStaff($otherWaiter)->getJson(route('live-orders.api'))
            ->assertOk()
            ->assertJsonCount(0, 'delivery_tasks');
    }

    /**
     * @return array{Restaurant, Order, Table, OrderItem, User, User}
     */
    private function operationContext(string $status = Order::STATUS_PREPARING, bool $assignWaiter = false): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Operation Tenant',
            'slug' => 'operation-'.Str::lower(Str::random(6)),
        ]);
        CurrentRestaurant::set($restaurant);

        $cashier = $this->staffUser($restaurant, User::ROLE_CASHIER);
        $waiter = $this->staffUser($restaurant, User::ROLE_WAITER);

        $table = Table::create([
            'restaurant_id' => $restaurant->id,
            'number' => (string) random_int(100, 999),
            'uuid' => (string) Str::uuid(),
            'qr_token' => Str::random(16),
            'is_active' => true,
            'status' => Table::STATUS_OCCUPIED,
            'assigned_user_id' => $assignWaiter ? $waiter->id : null,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => ['tr' => 'Test'],
            'slug' => 'test-'.Str::lower(Str::random(6)),
            'type' => 'kitchen',
            'is_active' => true,
        ]);

        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => ['tr' => 'Test Ürün'],
            'type' => 'kitchen',
            'station' => 'kitchen',
            'price' => '50.00',
            'is_available' => true,
            'in_stock' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'order_number' => 'D'.Str::upper(Str::random(10)),
            'status' => $status,
            'total' => '50.00',
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '50.00',
            'product_name' => 'Test Ürün',
            'preparation_status' => OrderItem::STATUS_WAITING,
        ]);

        return [$restaurant, $order, $table, $item, $cashier, $waiter];
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
