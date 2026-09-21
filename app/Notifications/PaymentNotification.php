<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'payment_'.$this->payment->status->value,
            'payment_id' => $this->payment->id,
            'invoice' => $this->payment->invoice_number,
            'total' => (float) $this->payment->total_amount,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pembayaran '.$this->payment->invoice_number.' ('.$this->payment->status->value.')')
            ->line('Total: Rp '.number_format((float) $this->payment->total_amount, 0, ',', '.'))
            ->action('Lihat Invoice', url('/payments/'.$this->payment->ulid));
    }
}
