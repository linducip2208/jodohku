<?php

namespace App\Jobs;

use App\Enums\CourtshipStatus;
use App\Models\Courtship;
use App\Notifications\CourtshipStageChanged;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Weekly nudge for stagnant taaruf journeys: active courtship untouched
 * for 14+ days. Uses the existing stage notification (deduped per
 * courtship) with a 'nudge' action — no new notification class needed.
 */
class SendTaarufReminders implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 300;

    public function handle(NotificationService $notifications): void
    {
        Courtship::where('status', CourtshipStatus::Active->value)
            ->where('updated_at', '<=', now()->subDays(14))
            ->with(['initiator', 'partner'])
            ->orderBy('id')->chunkById(200, function ($rows) use ($notifications) {
                foreach ($rows as $courtship) {
                    foreach ([$courtship->initiator, $courtship->partner] as $user) {
                        if (! $user) {
                            continue;
                        }
                        try {
                            $notifications->send($user, new CourtshipStageChanged($courtship, 'nudge'));
                        } catch (\Throwable) {
                        }
                    }
                }
            });
    }
}
