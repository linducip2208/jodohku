<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendChatNotification implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 60;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = Message::with(['conversation.members', 'sender'])->find($this->messageId);
        if (! $message) {
            return;
        }
        foreach ($message->conversation->members as $member) {
            if ((int) $member->user_id === (int) $message->sender_id || $member->left_at) {
                continue;
            }
            $user = User::find($member->user_id);
            if (! $user) {
                continue;
            }
            if ($user->notificationPreference && ! $user->notificationPreference->push_messages) {
                continue;
            }
            $user->notify(new NewMessage($message));
        }
    }
}
