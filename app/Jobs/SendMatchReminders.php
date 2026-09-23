<?php

namespace App\Jobs;

use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\UserMatch;
use App\Notifications\MatchNudge;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Weekly nudge for stagnant matches: active match, matched >3 days ago,
 * zero messages exchanged. Deduped per match via NotificationService.
 */
class SendMatchReminders implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notifications): void
    {
        UserMatch::where('is_active', true)
            ->where('matched_at', '<=', now()->subDays(3))
            ->with(['userA', 'userB'])
            ->orderBy('id')->chunkById(200, function ($matches) use ($notifications) {
                foreach ($matches as $match) {
                    $a = $match->userA;
                    $b = $match->userB;
                    if (! $a || ! $b) {
                        continue;
                    }
                    $convIds = ConversationMember::where('user_id', $a->id)->pluck('conversation_id')
                        ->intersect(ConversationMember::where('user_id', $b->id)->pluck('conversation_id'));
                    if ($convIds->isNotEmpty() && Message::whereIn('conversation_id', $convIds)->exists()) {
                        continue;
                    }
                    try {
                        $notifications->send($a, new MatchNudge($match, $b));
                        $notifications->send($b, new MatchNudge($match, $a));
                    } catch (\Throwable) {
                    }
                }
            });
    }
}
