<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code, public readonly int $ttlMinutes = 10) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Kode verifikasi Jodohku: '.$this->code)
            ->greeting('Halo '.$notifiable->displayName().'!')
            ->line('Kode verifikasi 2 langkah kamu:')
            ->line('# '.$this->code)
            ->line('Berlaku '.$this->ttlMinutes.' menit. Jangan bagikan ke siapa pun, termasuk yang mengaku admin Jodohku.')
            ->line('Abaikan email ini jika kamu tidak sedang masuk.');
    }
}
