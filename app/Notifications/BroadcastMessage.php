<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $broadcastId,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $prefs = $notifiable->notificationPreference;
        // Promotional broadcasts honor the email opt-out.
        if (! $prefs || $prefs->email_promotions) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'broadcast',
            'broadcast_id' => $this->broadcastId,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo '.$notifiable->displayName().'!')
            ->line($this->body);
    }
}
