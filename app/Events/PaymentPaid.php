<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPaid implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->payment->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'payment.paid';
    }

    public function broadcastWith(): array
    {
        return ['payment_id' => $this->payment->id, 'invoice' => $this->payment->invoice_number];
    }
}
