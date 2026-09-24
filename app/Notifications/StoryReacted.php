<?php

namespace App\Notifications;

use App\Models\Story;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StoryReacted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $actor, public Story $story, public string $type = 'like') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['type' => 'story_reacted', 'actor_id' => $this->actor->id, 'story_id' => $this->story->id, 'reaction' => $this->type, 'title' => $this->actor->displayName().' bereaksi pada story-mu'];
    }
}
