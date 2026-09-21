<?php

namespace App\Events;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user, public Subscription $subscription) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->user->id)];
    }

    public function broadcastAs(): string { return 'subscription.activated'; }

    public function broadcastWith(): array
    {
        return ['subscription_id' => $this->subscription->id, 'ends_at' => $this->subscription->ends_at?->toISOString()];
    }
}
