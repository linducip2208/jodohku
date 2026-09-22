<?php

namespace App\Events;

use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileVerified implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user, public VerificationRequest $request) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->user->id)];
    }

    public function broadcastAs(): string
    {
        return 'profile.verified';
    }

    public function broadcastWith(): array
    {
        return ['user_id' => $this->user->id, 'type' => $this->request->type->value ?? (string) $this->request->type];
    }
}
