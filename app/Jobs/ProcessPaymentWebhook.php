<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    /** Money must never be lost: more attempts, long backoff for gateway drift. */
    public $tries = 5;

    public $timeout = 120;

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(public string $gateway, public array $payload, public array $headers = []) {}

    public function handle(PaymentService $payments): void
    {
        $payments->handleWebhook($this->gateway, $this->payload, $this->headers);
    }
}
