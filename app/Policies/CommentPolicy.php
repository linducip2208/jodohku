<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return (int) $user->id === (int) $comment->user_id || $user->isStaff();
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ((int) $user->id === (int) $comment->user_id || $user->isStaff()) {
            return true;
        }
        // Post authors moderate their own threads.
        try {
            return (int) $comment->post?->user_id === (int) $user->id;
        } catch (\Throwable) {
            return false;
        }
    }

    public function view(?User $viewer, Comment $comment): bool
    {
        if (! $viewer) {
            return false;
        }
        if ($viewer->isStaff() || (int) $viewer->id === (int) $comment->user_id) {
            return true;
        }
        if (Block::existsBetween((int) $viewer->id, (int) $comment->user_id)) {
            return false;
        }

        return app(PostPolicy::class)->view($viewer, $comment->post);
    }
}
