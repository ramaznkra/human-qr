<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\Payment;

interface PosGateway
{
    /**
     * @return array<string, mixed>
     */
    public function initiatePayment(Order $order, Payment $payment): array;

    /**
     * @return array<string, mixed>
     */
    public function queryPayment(Payment $payment): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(Payment $payment): array;

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(Payment $payment, string $amount): array;
}
