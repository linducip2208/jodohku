<?php

namespace App\Listeners;

use App\Events\MutualMatchCreated;
use App\Events\ProfileLiked;
use App\Events\ProfileViewed;
use App\Events\UserRegistered;
use App\Jobs\ProcessVirtualChatTrigger;
use Illuminate\Events\Dispatcher;

class FireVirtualTrigger
{
    public function handleUserRegistered(UserRegistered $event): void
    {
        ProcessVirtualChatTrigger::dispatch('user.registered', $event->user->id);
    }

    public function handleProfileViewed(ProfileViewed $event): void
    {
        ProcessVirtualChatTrigger::dispatch('profile.viewed', $event->viewer->id, ['profile_user_id' => $event->profileOwner->id]);
    }

    public function handleProfileLiked(ProfileLiked $event): void
    {
        ProcessVirtualChatTrigger::dispatch('profile.liked', $event->liker->id, ['liked_id' => $event->liked->id]);
    }

    public function handleMutualMatch(MutualMatchCreated $event): void
    {
        ProcessVirtualChatTrigger::dispatch('match.created', $event->match->user_a_id, ['match_id' => $event->match->id]);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserRegistered::class, [self::class, 'handleUserRegistered']);
        $events->listen(ProfileViewed::class, [self::class, 'handleProfileViewed']);
        $events->listen(ProfileLiked::class, [self::class, 'handleProfileLiked']);
        $events->listen(MutualMatchCreated::class, [self::class, 'handleMutualMatch']);
    }
}
