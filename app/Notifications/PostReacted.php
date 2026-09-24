<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PostReacted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $actor, public Post $post, public string $type = 'like') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['type' => 'post_reacted', 'actor_id' => $this->actor->id, 'post_id' => $this->post->id, 'reaction' => $this->type, 'title' => $this->actor->displayName().' bereaksi pada postinganmu'];
    }
}
