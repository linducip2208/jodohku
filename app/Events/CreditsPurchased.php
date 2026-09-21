<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditsPurchased implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user, public int $amount) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->user->id)];
    }

    public function broadcastAs(): string { return 'credits.purchased'; }

    public function broadcastWith(): array
    {
        return ['amount' => $this->amount];
    }
}
