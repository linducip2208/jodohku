<?php

namespace App\Payments;

use App\Models\Payment;

class TripayGateway extends BaseGateway
{
    public function code(): string
    {
        return 'tripay';
    }

    public function createPayment(Payment $payment, array $options = []): array
    {
        $res = $this->http()->withHeaders(['Authorization' => 'Bearer '.(string) $this->cfg('api_key', '')])
            ->post(rtrim((string) $this->cfg('base_url'), '/').'/transaction/create', [
                'method' => $options['method'] ?? 'QRIS',
                'merchant_ref' => $payment->invoice_number,
                'amount' => (int) $payment->total_amount,
                'customer_name' => $payment->user?->displayName() ?? 'Customer',
                'customer_email' => $payment->user?->email,
                'order_items' => [[
                    'name' => 'Jodohku payment '.$payment->invoice_number,
                    'price' => (int) $payment->total_amount,
                    'quantity' => 1,
                ]],
                'expired_time' => now()->addHours(24)->timestamp,
                'signature' => hash_hmac('sha256', (string) $this->cfg('merchant_code', '').$payment->invoice_number.(int) $payment->total_amount, (string) $this->cfg('private_key', '')),
            ]);
        $data = $res->json() ?? [];
        $ref = $data['data']['reference'] ?? null;
        $payment->update([
            'gateway_transaction_id' => $ref ?? $payment->gateway_transaction_id,
            'gateway_response' => array_merge($payment->gateway_response ?? [], ['create' => $data]),
        ]);
        if (empty($data['data']['checkout_url'])) {
            throw new \RuntimeException('Tripay unavailable: no checkout URL returned.');
        }

        return ['gateway' => 'tripay', 'reference' => $ref, 'checkout_url' => $data['data']['checkout_url'] ?? null, 'raw' => $data];
    }

    public function verifyPayment(Payment $payment): array
    {
        $res = $this->http()->withHeaders(['Authorization' => 'Bearer '.(string) $this->cfg('api_key', '')])
            ->get(rtrim((string) $this->cfg('base_url'), '/').'/transaction/detail', ['reference' => $payment->gateway_transaction_id]);
        $data = $res->json() ?? [];

        return ['gateway' => 'tripay', 'status' => strtolower((string) ($data['data']['status'] ?? 'pending')), 'raw' => $data];
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        $signature = $headers['x-signature'] ?? $headers['X-Signature'] ?? request()->header('x-signature', '');
        $expected = hash_hmac('sha256', json_encode($payload), (string) $this->cfg('private_key', ''));
        if ($signature === '' || ! hash_equals($expected, (string) $signature)) {
            throw new \RuntimeException('Invalid or missing Tripay signature.');
        }
        $status = strtolower((string) ($payload['status'] ?? ''));

        return [
            'gateway' => 'tripay',
            'reference' => $payload['merchant_ref'] ?? $payload['reference'] ?? null,
            'status' => $status === 'paid' ? 'paid' : $status,
            'event_id' => 'tripay:'.($payload['reference'] ?? '').':'.$status,
            'raw' => $payload,
        ];
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        return ['gateway' => 'tripay', 'status' => 'manual_refund_required', 'amount' => $amount ?? (float) $payment->total_amount];
    }

    public function getStatus(Payment $payment): string
    {
        return $payment->status->value;
    }
}
