<?php

namespace App\Notifications;

use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissedCall extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Call $call) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'missed_call',
            'call_id' => $this->call->id,
            'call_type' => $this->call->type,
            'caller_id' => $this->call->caller_id,
            'caller_name' => $this->call->caller?->displayName(),
            'conversation_id' => $this->call->conversation_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Panggilan tak terjawab')
            ->line($this->call->caller?->displayName().' menghubungimu.');
    }
}
