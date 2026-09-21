<?php

namespace App\Events;

use App\Models\UserMatch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MutualMatchCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public UserMatch $match) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->match->user_a_id),
            new PrivateChannel('users.'.$this->match->user_b_id),
        ];
    }

    public function broadcastAs(): string { return 'match.created'; }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'user_a_id' => $this->match->user_a_id,
            'user_b_id' => $this->match->user_b_id,
            'compatibility_score' => $this->match->compatibility_score,
        ];
    }
}
