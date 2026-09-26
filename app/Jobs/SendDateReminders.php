<?php

namespace App\Jobs;

use App\Notifications\DateReminder;
use App\Services\DatePlanService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly H-24 reminders for accepted dates (idempotent via reminded_at).
 */
class SendDateReminders implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 300;

    public function handle(DatePlanService $dates, NotificationService $notifications): void
    {
        foreach ($dates->dueForReminder() as $plan) {
            try {
                if ($plan->proposer) {
                    $notifications->send($plan->proposer, new DateReminder($plan));
                }
                if ($plan->partner) {
                    $notifications->send($plan->partner, new DateReminder($plan));
                }
                $plan->update(['reminded_at' => now()]);
            } catch (\Throwable) {
            }
        }
    }
}
