<?php

namespace App\Jobs;

use App\Models\ProfileView;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Throttled profile-view writer (SCALE-AUDIT P1).
 *
 * ProfileViewed fires on every profile render; writing synchronously would
 * be 1 insert per view in the request path (and spammed the queue before
 * via the old listener). This job enforces one row per viewer→profile per
 * day, respects the owner's allow_profile_views flag, and no-ops on
 * self-views/deleted users. Controllers keep firing the event — the
 * listener dispatches here, never writes inline.
 */
class RecordProfileView implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 60;

    public function __construct(public int $profileUserId, public int $viewerId) {}

    public function handle(): void
    {
        if ($this->profileUserId === $this->viewerId) {
            return;
        }
        $owner = User::find($this->profileUserId);
        $viewer = User::find($this->viewerId);
        if (! $owner || ! $viewer) {
            return;
        }
        try {
            if ($owner->profilePrivacy && $owner->profilePrivacy->allow_profile_views === false) {
                return;
            }
        } catch (\Throwable) {
        }
        $already = ProfileView::where('profile_user_id', $this->profileUserId)
            ->where('viewer_id', $this->viewerId)
            ->whereDate('viewed_at', today())
            ->exists();
        if ($already) {
            return;
        }
        ProfileView::create([
            'profile_user_id' => $this->profileUserId,
            'viewer_id' => $this->viewerId,
            'viewed_at' => now(),
        ]);
        try {
            app(AnalyticsService::class)->capture($viewer, 'profile_view', $owner);
        } catch (\Throwable) {
        }
    }
}
