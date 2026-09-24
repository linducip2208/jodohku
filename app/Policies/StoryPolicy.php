<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\Story;
use App\Models\User;
use App\Models\UserMatch;

class StoryPolicy
{
    public function view(?User $viewer, Story $story): bool
    {
        if ($story->isExpired()) {
            return $viewer !== null && ((int) $viewer->id === (int) $story->user_id || $viewer->isStaff());
        }
        if (! $viewer) {
            return $story->visibility === 'public';
        }
        if ((int) $viewer->id === (int) $story->user_id || $viewer->isStaff()) {
            return true;
        }
        if (Block::existsBetween((int) $viewer->id, (int) $story->user_id)) {
            return false;
        }
        if ($story->visibility === 'matches_only') {
            return UserMatch::where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('user_a_id', $viewer->id)->where('user_b_id', $story->user_id))
                ->orWhere(fn ($qq) => $qq->where('user_a_id', $story->user_id)->where('user_b_id', $viewer->id)))
                ->where('is_active', true)->exists();
        }

        return in_array($story->visibility, ['public', 'members_only'], true);
    }

    public function delete(User $user, Story $story): bool
    {
        return (int) $user->id === (int) $story->user_id || $user->isStaff();
    }
}
