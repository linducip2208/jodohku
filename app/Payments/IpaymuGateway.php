<?php

namespace App\Payments;

use App\Models\Payment;

class IpaymuGateway extends BaseGateway
{
    public function code(): string { return 'ipaymu'; }

    public function createPayment(Payment $payment, array $options = []): array
    {
        $va = $this->cfg('va', '');
        $apiKey = $this->cfg('api_key', '');
        $body = [
            'product' => [$payment->invoice_number],
            'qty' => [1],
            'price' => [(float) $payment->total_amount],
            'returnUrl' => $options['return_url'] ?? url('/payments/'.$payment->ulid.'/return'),
            'cancelUrl' => $options['cancel_url'] ?? url('/payments/'.$payment->ulid.'/cancel'),
            'notifyUrl' => url('/webhooks/ipaymu'),
            'referenceId' => $payment->invoice_number,
            'buyerName' => $payment->user?->displayName() ?? 'Customer',
            'buyerEmail' => $payment->user?->email,
        ];
        $jsonBody = json_encode($body);
        $signature = hash_hmac('sha256', strtolower($jsonBody ?? ''), (string) $apiKey);

        $res = $this->http()->withHeaders([
            'va' => $va,
            'signature' => $signature,
            'timestamp' => now()->format('YmdHis'),
        ])->post(rtrim((string) $this->cfg('base_url'), '/').'/payment', $body);

        $data = $res->json() ?? [];
        $payment->update(['gateway_response' => array_merge($payment->gateway_response ?? [], ['create' => $data])]);

        return [
            'gateway' => 'ipaymu',
            'reference' => $data['Data']['TransactionId'] ?? $data['Data']['SessionId'] ?? null,
            'checkout_url' => $data['Data']['Url'] ?? null,
            'raw' => $data,
        ];
    }

    public function verifyPayment(Payment $payment): array
    {
        // iPaymu verify via transaction endpoint
        $res = $this->http()->withHeaders(['va' => $this->cfg('va', ''), 'signature' => hash_hmac('sha256', (string) $payment->invoice_number, (string) $this->cfg('api_key', ''))])
            ->get(rtrim((string) $this->cfg('base_url'), '/').'/transaction/'.$payment->invoice_number);

        return ['gateway' => 'ipaymu', 'status' => $payment->status->value, 'raw' => $res->json()];
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        $signature = $headers['signature'] ?? $headers['Signature'] ?? '';
        $expected = hash_hmac('sha256', json_encode($payload), (string) $this->cfg('api_key', ''));
        if ($signature && ! hash_equals($expected, (string) $signature)) {
            throw new \RuntimeException('Invalid iPaymu signature.');
        }
        $reference = $payload['reference_id'] ?? $payload['referenceId'] ?? null;
        $status = strtolower((string) ($payload['status'] ?? $payload['status_code'] ?? ''));

        return [
            'gateway' => 'ipaymu',
            'reference' => $reference,
            'status' => in_array($status, ['berhasil', 'success', 'paid', 'settlement'], true) ? 'paid' : 'pending',
            'event_id' => 'ipaymu:'.($reference ?? '').':'.($payload['trx_id'] ?? $status),
            'raw' => $payload,
        ];
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        return ['gateway' => 'ipaymu', 'status' => 'manual_refund_required', 'amount' => $amount ?? (float) $payment->total_amount];
    }

    public function getStatus(Payment $payment): string
    {
        return $payment->status->value;
    }
}
