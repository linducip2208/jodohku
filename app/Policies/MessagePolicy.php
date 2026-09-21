<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->involves((int) $user->id) || $user->isStaff();
    }

    public function update(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id || $user->isStaff();
    }
}
