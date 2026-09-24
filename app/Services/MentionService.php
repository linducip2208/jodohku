<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Mention;
use App\Models\Mute;
use App\Models\User;
use App\Notifications\Mentioned;
use Illuminate\Database\Eloquent\Model;

/**
 * @username mentions in posts/comments. Mentioned users get one
 * notification each (blocked/muted authors are still recorded but never
 * notified — the mention row is the audit trail).
 */
class MentionService
{
    public static function extractUsernames(string $body): array
    {
        preg_match_all('/@([A-Za-z0-9_.-]{3,50})/', $body, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** @return int mentions created */
    public function sync(Model $mentionable, string $body, User $author): int
    {
        $created = 0;
        foreach (self::extractUsernames($body) as $username) {
            $mentioned = User::where('username', $username)->first();
            if (! $mentioned || (int) $mentioned->id === (int) $author->id) {
                continue;
            }
            $row = Mention::firstOrCreate([
                'mentionable_type' => $mentionable->getMorphClass(),
                'mentionable_id' => $mentionable->getKey(),
                'mentioned_user_id' => $mentioned->id,
            ], ['mentioned_by' => $author->id]);
            if ($row->wasRecentlyCreated) {
                $created++;
                try {
                    if (! Block::existsBetween((int) $author->id, (int) $mentioned->id)
                        && ! Mute::existsBetween((int) $mentioned->id, (int) $author->id)) {
                        app(NotificationService::class)->send($mentioned, new Mentioned($author, $mentionable));
                    }
                } catch (\Throwable) {
                }
            }
        }

        return $created;
    }
}
