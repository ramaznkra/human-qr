<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $restaurant = Restaurant::query()->first()
            ?? Restaurant::create([
                'name' => 'Test Restaurant',
                'slug' => 'test-'.Str::lower(Str::random(8)),
            ]);

        $order = Order::query()->forRestaurant($restaurant->id)->first()
            ?? Order::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurant->id,
                'order_number' => 'T'.Str::upper(Str::random(10)),
                'status' => Order::STATUS_DELIVERED,
                'total' => '100.00',
            ]);

        return [
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'table_id' => $order->table_id,
            'user_id' => null,
            'method' => Payment::METHOD_CASH,
            'mode' => Payment::MODE_MANUAL_CASH,
            'provider' => null,
            'status' => Payment::STATUS_SUCCESS,
            'amount' => '100.00',
            'currency' => 'TRY',
            'reference' => 'PAY-'.Str::upper(Str::random(16)),
            'idempotency_key' => null,
            'completed_at' => now(),
        ];
    }

    public function successfulCash(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => Payment::METHOD_CASH,
            'mode' => Payment::MODE_MANUAL_CASH,
            'provider' => null,
            'status' => Payment::STATUS_SUCCESS,
            'completed_at' => now(),
        ]);
    }

    public function successfulManualCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => Payment::METHOD_CARD,
            'mode' => Payment::MODE_MANUAL_CARD,
            'provider' => null,
            'status' => Payment::STATUS_SUCCESS,
            'completed_at' => now(),
        ]);
    }

    public function pendingPos(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => Payment::METHOD_CARD,
            'mode' => Payment::MODE_POS_CARD,
            'provider' => 'pos',
            'status' => Payment::STATUS_PENDING,
            'completed_at' => null,
        ]);
    }

    public function failedPos(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => Payment::METHOD_CARD,
            'mode' => Payment::MODE_POS_CARD,
            'provider' => 'pos',
            'status' => Payment::STATUS_FAILED,
            'failure_code' => 'pos_failed',
            'failure_message' => 'POS payment failed.',
            'completed_at' => null,
        ]);
    }

    public function unknownPos(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => Payment::METHOD_CARD,
            'mode' => Payment::MODE_POS_CARD,
            'provider' => 'pos',
            'status' => Payment::STATUS_UNKNOWN,
            'completed_at' => null,
        ]);
    }
}
