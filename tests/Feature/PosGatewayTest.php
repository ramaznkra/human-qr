<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\Table;
use App\Services\Pos\DisabledPosGateway;
use App\Services\Pos\FakePosGateway;
use App\Services\Pos\PosGateway;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PosGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CurrentRestaurant::clear();

        parent::tearDown();
    }

    public function test_disabled_gateway_does_not_produce_successful_result(): void
    {
        [$order, $payment] = $this->createPaymentContext();

        $result = (new DisabledPosGateway)->initiatePayment($order, $payment);

        $this->assertFalse($result['success']);
        $this->assertSame(Payment::STATUS_FAILED, $result['status']);
        $this->assertSame(Payment::STATUS_CREATED, $payment->fresh()->status);
    }

    public function test_fake_gateway_works_in_local_environment(): void
    {
        $this->setAppEnvironment('local');
        config()->set('pos.fake_result', 'success');
        [$order, $payment] = $this->createPaymentContext();

        $result = (new FakePosGateway($this->app))->initiatePayment($order, $payment);

        $this->assertTrue($result['success']);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
    }

    public function test_fake_gateway_works_in_testing_environment(): void
    {
        $this->setAppEnvironment('testing');
        config()->set('pos.fake_result', 'failed');
        [$order, $payment] = $this->createPaymentContext();

        $result = (new FakePosGateway($this->app))->initiatePayment($order, $payment);

        $this->assertFalse($result['success']);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_fake_gateway_can_simulate_unknown_result(): void
    {
        $this->setAppEnvironment('testing');
        config()->set('pos.fake_result', 'unknown');
        [$order, $payment] = $this->createPaymentContext();

        $result = (new FakePosGateway($this->app))->initiatePayment($order, $payment);

        $this->assertFalse($result['success']);
        $this->assertSame(Payment::STATUS_UNKNOWN, $result['status']);
        $this->assertSame(Payment::STATUS_UNKNOWN, $payment->fresh()->status);
    }

    public function test_fake_gateway_is_rejected_in_production_environment(): void
    {
        $this->setAppEnvironment('production');
        [$order, $payment] = $this->createPaymentContext();

        $this->expectException(RuntimeException::class);

        (new FakePosGateway($this->app))->initiatePayment($order, $payment);
    }

    public function test_auto_complete_cannot_be_active_in_production(): void
    {
        $this->setAppEnvironment('production');
        config()->set('pos.driver', 'fake');
        config()->set('pos.auto_complete', true);

        $this->assertInstanceOf(DisabledPosGateway::class, app(PosGateway::class));
    }

    public function test_container_resolves_fake_gateway_for_fake_driver(): void
    {
        $this->setAppEnvironment('testing');
        config()->set('pos.driver', 'fake');
        config()->set('pos.auto_complete', false);

        $this->assertInstanceOf(FakePosGateway::class, app(PosGateway::class));
    }

    public function test_container_resolves_disabled_gateway_for_disabled_driver(): void
    {
        config()->set('pos.driver', 'disabled');

        $this->assertInstanceOf(DisabledPosGateway::class, app(PosGateway::class));
    }

    public function test_unknown_driver_falls_back_to_disabled_gateway(): void
    {
        config()->set('pos.driver', 'mystery');

        $this->assertInstanceOf(DisabledPosGateway::class, app(PosGateway::class));
    }

    /**
     * @return array{Order, Payment}
     */
    private function createPaymentContext(): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Gateway Test',
            'slug' => 'gateway-test-'.Str::lower(Str::random(6)),
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
            'order_number' => 'G'.Str::upper(Str::random(10)),
            'status' => Order::STATUS_DELIVERED,
            'total' => '100.00',
        ]);

        $payment = Payment::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'table_id' => $table->id,
            'method' => Payment::METHOD_CARD,
            'mode' => Payment::MODE_POS_CARD,
            'provider' => null,
            'status' => Payment::STATUS_CREATED,
            'amount' => '100.00',
            'currency' => 'TRY',
            'reference' => 'TEST-'.Str::upper(Str::random(12)),
        ]);

        return [$order, $payment];
    }

    private function setAppEnvironment(string $environment): void
    {
        $this->app['env'] = $environment;
        config()->set('app.env', $environment);
    }
}
