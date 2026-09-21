<?php

namespace App\Listeners;

use App\Events\MutualMatchCreated;
use App\Models\User;
use App\Notifications\MatchFound;

class SendMatchNotification
{
    public function handle(MutualMatchCreated $event): void
    {
        $a = User::find($event->match->user_a_id);
        $b = User::find($event->match->user_b_id);
        if (! $a || ! $b) {
            return;
        }
        if (! $a->notificationPreference || $a->notificationPreference->push_matches) {
            $a->notify(new MatchFound($event->match, $b));
        }
        if (! $b->notificationPreference || $b->notificationPreference->push_matches) {
            $b->notify(new MatchFound($event->match, $a));
        }
    }
}
