<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class Mentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $author, public Model $mentionable) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'mentioned', 'author_id' => $this->author->id,
            'mentionable_type' => $this->mentionable->getMorphClass(),
            'mentionable_id' => $this->mentionable->getKey(),
            'title' => $this->author->displayName().' menyebutmu',
        ];
    }
}
