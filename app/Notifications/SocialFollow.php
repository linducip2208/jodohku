<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SocialFollow extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $follower) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['type' => 'social_follow', 'follower_id' => $this->follower->id, 'title' => $this->follower->displayName().' mulai mengikutimu'];
    }
}
