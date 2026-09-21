<?php

namespace App\Payments;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function code(): string;

    /** Create payment at gateway, return gateway reference + redirect/checkout data. */
    public function createPayment(Payment $payment, array $options = []): array;

    public function verifyPayment(Payment $payment): array;

    /** Handle inbound webhook payload. Must be idempotent. Returns normalized result. */
    public function handleWebhook(array $payload, array $headers = []): array;

    public function refund(Payment $payment, ?float $amount = null): array;

    public function getStatus(Payment $payment): string;
}
