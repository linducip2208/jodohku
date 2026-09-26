<?php

namespace App\Notifications;

use App\Models\DatePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DateProposed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DatePlan $plan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Ajakan kencan 💘',
            'body' => $this->plan->proposer?->displayName().' mengajakmu kencan'.($this->plan->place ? ' di '.$this->plan->place : '').'.',
            'type' => 'date_proposed',
            'date_plan_id' => $this->plan->id,
            'proposer_id' => $this->plan->proposer_id,
            'scheduled_at' => $this->plan->scheduled_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ajakan kencan 💘')
            ->line($this->plan->proposer?->displayName().' mengajakmu kencan.')
            ->action('Lihat ajakan', url('/dates'));
    }
}
