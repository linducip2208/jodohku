<?php

namespace App\Notifications;

use App\Models\ChatRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ChatRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'chat_request',
            'request_id' => $this->request->id,
            'sender_id' => $this->request->sender_id,
            'preview' => mb_substr((string) $this->request->message, 0, 120),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permintaan chat baru')
            ->line($this->request->sender?->displayName().' ingin mengobrol denganmu.')
            ->action('Tanggapi', url('/chat-requests/'.$this->request->id));
    }
}
