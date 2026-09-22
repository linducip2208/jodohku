<?php

namespace App\Payments;

use App\Models\Payment;

class MidtransGateway extends BaseGateway
{
    public function code(): string
    {
        return 'midtrans';
    }

    public function createPayment(Payment $payment, array $options = []): array
    {
        $serverKey = (string) $this->cfg('server_key', '');
        $res = $this->http()->withBasicAuth($serverKey, '')
            ->post(rtrim((string) $this->cfg('base_url'), '/').'/v1/payment-links', [
                'transaction_details' => ['order_id' => $payment->invoice_number, 'gross_amount' => (float) $payment->total_amount],
                'customer_details' => ['email' => $payment->user?->email, 'first_name' => $payment->user?->displayName()],
                'expiry' => ['duration' => 24, 'unit' => 'hours'],
            ]);
        // Fallback to Snap if payment-links unavailable
        $data = $res->json() ?? [];
        if (empty($data) || ! $res->successful()) {
            $snap = $this->http()->withBasicAuth($serverKey, '')
                ->post('https://app.sandbox.midtrans.com/snap/v1/transactions', [
                    'transaction_details' => ['order_id' => $payment->invoice_number, 'gross_amount' => (float) $payment->total_amount],
                ]);
            $data = $snap->json() ?? [];
        }
        $payment->update(['gateway_response' => array_merge($payment->gateway_response ?? [], ['create' => $data])]);
        if (empty($data['payment_url']) && empty($data['redirect_url']) && empty($data['token'])) {
            throw new \RuntimeException('Midtrans unavailable: no checkout URL returned.');
        }

        return ['gateway' => 'midtrans', 'reference' => $payment->invoice_number, 'checkout_url' => $data['payment_url'] ?? $data['redirect_url'] ?? null, 'raw' => $data];
    }

    public function verifyPayment(Payment $payment): array
    {
        $serverKey = (string) $this->cfg('server_key', '');
        $res = $this->http()->withBasicAuth($serverKey, '')
            ->get(rtrim((string) $this->cfg('base_url'), '/').'/v2/'.$payment->invoice_number.'/status');
        $data = $res->json() ?? [];

        return ['gateway' => 'midtrans', 'status' => strtolower((string) ($data['transaction_status'] ?? 'pending')), 'raw' => $data];
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? '';
        $gross = $payload['gross_amount'] ?? '';
        $serverKey = (string) $this->cfg('server_key', '');
        $expected = hash('sha512', $orderId.$statusCode.$gross.$serverKey);
        $signature = $payload['signature_key'] ?? '';
        if ($signature === '' || ! hash_equals($expected, (string) $signature)) {
            throw new \RuntimeException('Invalid or missing Midtrans signature.');
        }
        $trx = strtolower((string) ($payload['transaction_status'] ?? ''));
        $status = in_array($trx, ['capture', 'settlement'], true) ? 'paid' : $trx;

        return [
            'gateway' => 'midtrans',
            'reference' => $orderId,
            'status' => $status,
            'event_id' => 'midtrans:'.$orderId.':'.$trx,
            'raw' => $payload,
        ];
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        $serverKey = (string) $this->cfg('server_key', '');
        $res = $this->http()->withBasicAuth($serverKey, '')
            ->post(rtrim((string) $this->cfg('base_url'), '/').'/v2/'.$payment->invoice_number.'/refund', [
                'amount' => $amount ?? (float) $payment->total_amount,
                'reason' => 'customer_request',
            ])->throw();

        return ['gateway' => 'midtrans', 'status' => 'refunded', 'raw' => $res->json()];
    }

    public function getStatus(Payment $payment): string
    {
        return $payment->status->value;
    }
}
