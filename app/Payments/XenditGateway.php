<?php

namespace App\Payments;

use App\Models\Payment;

class XenditGateway extends BaseGateway
{
    public function code(): string { return 'xendit'; }

    public function createPayment(Payment $payment, array $options = []): array
    {
        $res = $this->http()->withBasicAuth((string) $this->cfg('api_key', ''), '')
            ->post(rtrim((string) $this->cfg('base_url'), '/').'/v2/invoices', [
                'external_id' => $payment->invoice_number,
                'amount' => (float) $payment->total_amount,
                'currency' => $payment->currency ?? 'IDR',
                'payer_email' => $payment->user?->email,
                'description' => 'Jodohku payment '.$payment->invoice_number,
                'success_redirect_url' => $options['return_url'] ?? url('/payments/'.$payment->ulid.'/return'),
                'failure_redirect_url' => $options['cancel_url'] ?? url('/payments/'.$payment->ulid.'/cancel'),
            ]);
        $data = $res->json() ?? [];
        $payment->update([
            'gateway_transaction_id' => $data['id'] ?? $payment->gateway_transaction_id,
            'gateway_response' => array_merge($payment->gateway_response ?? [], ['create' => $data]),
        ]);
        if (empty($data['invoice_url'])) {
            throw new \RuntimeException('Xendit unavailable: no invoice URL returned.');
        }

        return ['gateway' => 'xendit', 'reference' => $data['id'] ?? null, 'checkout_url' => $data['invoice_url'] ?? null, 'raw' => $data];
    }

    public function verifyPayment(Payment $payment): array
    {
        $res = $this->http()->withBasicAuth((string) $this->cfg('api_key', ''), '')
            ->get(rtrim((string) $this->cfg('base_url'), '/').'/v2/invoices/'.$payment->invoice_number);
        $data = $res->json() ?? [];

        return ['gateway' => 'xendit', 'status' => strtolower((string) ($data['status'] ?? 'pending')), 'raw' => $data];
    }

    public function handleWebhook(array $payload, array $headers = []): array
    {
        $token = $headers['x-callback-token'] ?? $headers['X-Callback-Token'] ?? request()->header('x-callback-token');
        $expected = (string) $this->cfg('webhook_token', '');
        if ($expected === '' || $token !== $expected) {
            throw new \RuntimeException('Invalid or missing Xendit callback token. Configure XENDIT_WEBHOOK_TOKEN.');
        }
        $status = strtolower((string) ($payload['status'] ?? ''));

        return [
            'gateway' => 'xendit',
            'reference' => $payload['external_id'] ?? null,
            'status' => in_array($status, ['paid', 'settled'], true) ? 'paid' : strtolower($status ?: 'pending'),
            'event_id' => 'xendit:'.($payload['id'] ?? '').':'.($payload['external_id'] ?? ''),
            'raw' => $payload,
        ];
    }

    public function refund(Payment $payment, ?float $amount = null): array
    {
        $res = $this->http()->withBasicAuth((string) $this->cfg('api_key', ''), '')
            ->post(rtrim((string) $this->cfg('base_url'), '/').'/refunds', [
                'invoice_id' => $payment->gateway_transaction_id,
                'amount' => $amount ?? (float) $payment->total_amount,
                'reason' => 'customer_request',
            ])->throw();

        return ['gateway' => 'xendit', 'status' => 'refunded', 'raw' => $res->json()];
    }

    public function getStatus(Payment $payment): string
    {
        return $payment->status->value;
    }
}
