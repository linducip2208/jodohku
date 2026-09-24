<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Events\MutualMatchCreated;
use App\Events\ProfileLiked;
use App\Events\UserRegistered;
use App\Services\AnalyticsService;
use Illuminate\Events\Dispatcher;

/**
 * Privacy-conscious analytics fan-in: domain events become queued
 * analytics rows. Never blocks, never carries bodies/locations.
 */
class AnalyticsListener
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function handleRegistered(UserRegistered $e): void
    {
        $this->analytics->capture($e->user, 'user_registered');
    }

    public function handleLiked(ProfileLiked $e): void
    {
        $this->analytics->capture($e->liker, 'dating_like', $e->liked);
    }

    public function handleMatch(MutualMatchCreated $e): void
    {
        $this->analytics->capture(null, 'match_created', $e->match);
    }

    public function handleMessage(MessageSent $e): void
    {
        $this->analytics->capture($e->message->sender, 'message_sent', $e->message);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserRegistered::class, [self::class, 'handleRegistered']);
        $events->listen(ProfileLiked::class, [self::class, 'handleLiked']);
        $events->listen(MutualMatchCreated::class, [self::class, 'handleMatch']);
        $events->listen(MessageSent::class, [self::class, 'handleMessage']);
    }
}
