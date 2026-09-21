<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileLiked implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $liker, public User $liked, public bool $isNew = true) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->liked->id)];
    }

    public function broadcastAs(): string { return 'profile.liked'; }

    public function broadcastWith(): array
    {
        return ['liker_id' => $this->liker->id, 'liked_id' => $this->liked->id];
    }
}
