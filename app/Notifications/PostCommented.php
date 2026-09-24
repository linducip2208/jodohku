<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PostCommented extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $actor, public Comment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['type' => 'post_commented', 'actor_id' => $this->actor->id, 'comment_id' => $this->comment->id, 'post_id' => $this->comment->post_id, 'title' => $this->actor->displayName().' mengomentari postinganmu'];
    }
}
