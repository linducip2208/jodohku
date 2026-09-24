<?php

namespace App\Listeners;

use App\Events\ProfileViewed;
use App\Jobs\RecordProfileView;

/**
 * ProfileViewed → throttled async writer (never sync-per-view).
 */
class RecordProfileViewListener
{
    public function handle(ProfileViewed $event): void
    {
        RecordProfileView::dispatch($event->profileOwner->id, $event->viewer->id);
    }
}
