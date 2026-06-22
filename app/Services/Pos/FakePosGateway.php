<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use RuntimeException;

class FakePosGateway implements PosGateway
{
    public function __construct(private readonly Application $app) {}

    public function initiatePayment(Order $order, Payment $payment): array
    {
        $this->ensureAllowedEnvironment();

        $status = $this->fakeStatus();
        $success = $status === Payment::STATUS_SUCCESS;

        $payment->update([
            'status' => $status,
            'provider' => 'fake',
            'terminal_id' => config('pos.terminal_id'),
            'provider_transaction_id' => 'FAKE-'.Str::upper(Str::random(12)),
            'response_payload' => [
                'driver' => 'fake',
                'result' => config('pos.fake_result', 'success'),
            ],
            'completed_at' => $success ? now() : null,
        ]);

        return [
            'success' => $success,
            'status' => $status,
            'reference' => $payment->reference,
            'message' => $success
                ? 'Fake POS payment approved.'
                : 'Fake POS payment simulated as '.$status.'.',
        ];
    }

    public function queryPayment(Payment $payment): array
    {
        $this->ensureAllowedEnvironment();

        return [
            'success' => $payment->isSuccessful(),
            'status' => $payment->status,
            'reference' => $payment->reference,
        ];
    }

    public function cancelPayment(Payment $payment): array
    {
        $this->ensureAllowedEnvironment();

        $payment->update([
            'status' => Payment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return [
            'success' => false,
            'status' => Payment::STATUS_CANCELLED,
            'reference' => $payment->reference,
            'message' => 'Fake POS payment cancelled.',
        ];
    }

    public function refundPayment(Payment $payment, string $amount): array
    {
        $this->ensureAllowedEnvironment();

        $payment->update([
            'status' => Payment::STATUS_REFUNDED,
            'refunded_at' => now(),
            'response_payload' => array_merge($payment->response_payload ?? [], [
                'refund_amount' => $amount,
            ]),
        ]);

        return [
            'success' => true,
            'status' => Payment::STATUS_REFUNDED,
            'reference' => $payment->reference,
            'amount' => $amount,
            'message' => 'Fake POS payment refunded.',
        ];
    }

    private function ensureAllowedEnvironment(): void
    {
        if (! $this->app->environment(['local', 'testing'])) {
            throw new RuntimeException('Fake POS gateway is allowed only in local and testing environments.');
        }
    }

    private function fakeStatus(): string
    {
        return match (config('pos.fake_result', 'success')) {
            'success' => Payment::STATUS_SUCCESS,
            'failed' => Payment::STATUS_FAILED,
            'unknown' => Payment::STATUS_UNKNOWN,
            default => Payment::STATUS_UNKNOWN,
        };
    }
}
