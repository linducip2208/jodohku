<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\RecalculateMatches;

/**
 * Register-time score warming (SCALE-AUDIT P1/C6).
 *
 * New users start with zero match_scores, so their first discovery pays
 * full live-compute cost — a registration wave hammers the pool. Warming
 * reuses MatchingEngine::scoreMany via the existing RecalculateMatches job
 * (async, 2 attempts/10 min), so the request stays fast and picks are warm
 * by the time the user opens discovery.
 */
class WarmNewUserMatches
{
    public function handle(UserRegistered $event): void
    {
        RecalculateMatches::dispatch($event->user->id, 20);
    }
}
