<?php

namespace App\Notifications;

use App\Models\Consultation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsultationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Consultation $consultation) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'consultation_status',
            'consultation_id' => $this->consultation->id,
            'status' => $this->consultation->status->value,
            'scheduled_at' => $this->consultation->scheduled_at?->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Jadwal konsultasi: '.$this->consultation->status->label())
            ->line('Konsultasi "'.$this->consultation->topic.'" berstatus: '.$this->consultation->status->label().'.')
            ->action('Lihat Konsultasi', url('/biro-jodoh/konsultasi'));
    }
}
