<?php

namespace App\Services;

use App\Jobs\RecordAnalyticsEvent;
use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Privacy-conscious analytics: fire-and-forget into the queue, never
 * blocking. No IPs, no message bodies, no exact locations — only event
 * names + subject ids + small safe meta.
 */
class AnalyticsService
{
    public function capture(?User $user, string $event, mixed $subject = null, array $meta = []): void
    {
        try {
            $subjectType = null;
            $subjectId = null;
            if ($subject instanceof Model) {
                $subjectType = $subject->getMorphClass();
                $subjectId = $subject->getKey();
            }
            RecordAnalyticsEvent::dispatch(
                $user?->id, $event, $subjectType, $subjectId,
                array_intersect_key($meta, array_flip(['type', 'source', 'value']))
            );
        } catch (\Throwable) {
            // Analytics must never break product flows.
        }
    }

    public function counts(string $event, int $days = 30): array
    {
        return AnalyticsEvent::where('event', $event)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) d, COUNT(*) c')
            ->groupBy('d')->orderBy('d')->pluck('c', 'd')->all();
    }
}
