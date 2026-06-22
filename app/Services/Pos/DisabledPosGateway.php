<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\Payment;

class DisabledPosGateway implements PosGateway
{
    public function initiatePayment(Order $order, Payment $payment): array
    {
        return $this->disabledResult('POS driver is disabled. No payment was initiated.');
    }

    public function queryPayment(Payment $payment): array
    {
        return $this->disabledResult('POS driver is disabled. Payment status cannot be queried.');
    }

    public function cancelPayment(Payment $payment): array
    {
        return $this->disabledResult('POS driver is disabled. Payment cannot be cancelled.');
    }

    public function refundPayment(Payment $payment, string $amount): array
    {
        return $this->disabledResult('POS driver is disabled. Payment cannot be refunded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function disabledResult(string $message): array
    {
        return [
            'success' => false,
            'status' => Payment::STATUS_FAILED,
            'message' => $message,
        ];
    }
}
