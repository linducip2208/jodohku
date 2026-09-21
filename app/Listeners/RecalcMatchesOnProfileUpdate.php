<?php

namespace App\Listeners;

use App\Events\ProfileViewed;
use App\Jobs\RecalculateMatches;

class RecalcMatchesOnProfileUpdate
{
    public function handle(ProfileViewed $event): void
    {
        // Lightweight: recalc viewer's top matches when they view profiles.
        RecalculateMatches::dispatch($event->viewer->id, 15);
    }
}
