<?php

namespace App\Notifications;

use App\Models\DatePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DateReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DatePlan $plan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $other = (int) $notifiable->id === (int) $this->plan->proposer_id
            ? $this->plan->partner : $this->plan->proposer;

        return [
            'title' => 'Kencan segera tiba ⏰',
            'body' => 'Kencanmu dengan '.$other?->displayName().' '.($this->plan->scheduled_at?->diffForHumans() ?? 'segera').($this->plan->place ? ' di '.$this->plan->place : '').'.',
            'type' => 'date_reminder',
            'date_plan_id' => $this->plan->id,
            'scheduled_at' => $this->plan->scheduled_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengingat kencan ⏰')
            ->line('Kencanmu sudah dekat. Persiapkan yang terbaik!')
            ->action('Lihat rencana', url('/dates'));
    }
}
