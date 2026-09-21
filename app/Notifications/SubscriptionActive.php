<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActive extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'subscription_active',
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->plan?->name,
            'ends_at' => $this->subscription->ends_at?->toISOString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Langganan Premium aktif')
            ->line('Paket '.$this->subscription->plan?->name.' aktif sampai '.$this->subscription->ends_at?->format('d M Y').'.')
            ->action('Kelola Langganan', url('/subscription'));
    }
}
