<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        if (! $conversation->involves((int) $user->id)) {
            return $user->isStaff();
        }
        $other = $conversation->otherMember((int) $user->id);
        if ($other && Block::existsBetween((int) $user->id, (int) $other->user_id)) {
            return false;
        }

        return true;
    }

    public function send(User $user, Conversation $conversation): bool
    {
        if (! $conversation->involves((int) $user->id) || $conversation->is_blocked) {
            return false;
        }
        $other = $conversation->otherMember((int) $user->id);
        if ($other && Block::existsBetween((int) $user->id, (int) $other->user_id)) {
            return false;
        }

        return $user->canChat();
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        return $conversation->involves((int) $user->id) || $user->isStaff();
    }
}
