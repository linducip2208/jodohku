<?php

namespace App\Policies;

use App\Enums\PrivacyVisibility;
use App\Models\Block;
use App\Models\Post;
use App\Models\User;
use App\Models\UserMatch;

class PostPolicy
{
    public function viewAny(?User $viewer): bool
    {
        return true;
    }

    public function view(?User $viewer, Post $post): bool
    {
        if ($post->is_hidden) {
            return $viewer !== null && ($viewer->isStaff() || (int) $viewer->id === (int) $post->user_id);
        }
        if ($viewer && ((int) $viewer->id === (int) $post->user_id || $viewer->isStaff())) {
            return true;
        }
        if ($viewer && Block::existsBetween((int) $viewer->id, (int) $post->user_id)) {
            return false;
        }
        $vis = $post->visibility instanceof PrivacyVisibility ? $post->visibility : PrivacyVisibility::tryFrom((string) $post->visibility);

        return match ($vis) {
            PrivacyVisibility::Public => true,
            PrivacyVisibility::MembersOnly => $viewer !== null,
            PrivacyVisibility::PremiumOnly => $viewer !== null && $viewer->isPremium(),
            PrivacyVisibility::MatchesOnly => $viewer !== null && UserMatch::where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('user_a_id', $viewer->id)->where('user_b_id', $post->user_id))
                ->orWhere(fn ($qq) => $qq->where('user_a_id', $post->user_id)->where('user_b_id', $viewer->id)))
                ->where('is_active', true)->exists(),
            default => false,
        };
    }

    public function update(User $user, Post $post): bool
    {
        return (int) $user->id === (int) $post->user_id || $user->isStaff();
    }

    public function delete(User $user, Post $post): bool
    {
        return (int) $user->id === (int) $post->user_id || $user->isStaff();
    }
}
