<?php

namespace App\Notifications;

use App\Models\Courtship;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourtshipStageChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Courtship $courtship, public string $action) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'courtship_stage',
            'courtship_id' => $this->courtship->id,
            'stage' => $this->courtship->stage->value,
            'status' => $this->courtship->status->value,
            'action' => $this->action,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stage = $this->courtship->stage->label();

        return (new MailMessage)
            ->subject('Update perjalanan taaruf: '.$stage)
            ->line('Tahapan taaruf kamu berubah: '.$stage.'.')
            ->action('Lihat Perjalanan', url('/biro-jodoh/taaruf/'.$this->courtship->id));
    }
}
