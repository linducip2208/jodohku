<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $gateway, public array $payload, public array $headers = []) {}

    public function handle(PaymentService $payments): void
    {
        $payments->handleWebhook($this->gateway, $this->payload, $this->headers);
    }
}
