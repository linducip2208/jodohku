<?php

namespace App\Services;

use App\Events\MutualMatchCreated;
use App\Events\ProfileLiked;
use App\Models\Block;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\Rewind;
use App\Models\SuperLike;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LikeService
{
    public function __construct(protected MatchingEngine $engine) {}

    protected function guard(User $a, User $b): void
    {
        if ($a->id === $b->id) {
            throw new \InvalidArgumentException('Cannot interact with yourself.');
        }
        if (Block::existsBetween((int) $a->id, (int) $b->id)) {
            throw new \RuntimeException('Interaction blocked.');
        }
    }

    /** Free members are capped per day; premium is unlimited. Re-likes don't consume quota. */
    protected function enforceDailyLikeLimit(User $liker): void
    {
        if ($liker->isPremium()) {
            return;
        }
        $limit = (int) config('jodohku.limits.free_daily_likes', 20);
        $used = Like::where('liker_id', $liker->id)->whereDate('created_at', today())->count();
        if ($used >= $limit) {
            throw new \RuntimeException('Daily like limit reached. Upgrade to Premium for unlimited likes.');
        }
    }

    public function likesRemainingToday(User $user): int|string
    {
        if ($user->isPremium()) {
            return 'unlimited';
        }
        $limit = (int) config('jodohku.limits.free_daily_likes', 20);
        $used = Like::where('liker_id', $user->id)->whereDate('created_at', today())->count();

        return max($limit - $used, 0);
    }

    public function like(User $liker, User $liked, bool $isSuper = false): array
    {
        $this->guard($liker, $liked);
        // Re-likes don't consume quota: only enforce when no row exists yet.
        $alreadyLiked = Like::where('liker_id', $liker->id)->where('liked_id', $liked->id)->exists();
        if (! $alreadyLiked) {
            $this->enforceDailyLikeLimit($liker);
        }

        return DB::transaction(function () use ($liker, $liked, $isSuper) {
            $like = Like::firstOrCreate(
                ['liker_id' => $liker->id, 'liked_id' => $liked->id],
                ['is_super' => $isSuper]
            );
            if ($isSuper && ! $like->is_super) {
                $like->update(['is_super' => true]);
            }

            $match = Like::createsMatch($liker, $liked, $like);
            $isNewMatch = false;
            if ($match && $match->wasRecentlyCreated) {
                $isNewMatch = true;
                try {
                    $score = $this->engine->scorePair($liker, $liked);
                    $match->update(['compatibility_score' => $score['mutual']]);
                } catch (\Throwable) {
                }
                event(new MutualMatchCreated($match->fresh()));
            }
            event(new ProfileLiked($liker, $liked, (bool) $like->wasRecentlyCreated));
            $this->bustMatchCaches($liker, $liked);

            return ['like' => $like->fresh(), 'match' => $match?->fresh(), 'is_new_match' => $isNewMatch];
        });
    }

    public function unlike(User $liker, User $liked): bool
    {
        return (bool) DB::transaction(function () use ($liker, $liked) {
            $like = Like::where('liker_id', $liker->id)->where('liked_id', $liked->id)->first();
            if (! $like) {
                return true; // idempotent
            }
            $like->delete();
            $this->deactivateActiveMatch($liker, $liked);
            $this->bustMatchCaches($liker, $liked);

            return true;
        });
    }

    protected function bustMatchCaches(User $a, User $b): void
    {
        try {
            [$u1, $u2] = UserMatch::canonical((int) $a->id, (int) $b->id);
            $version = app(MatchingEngine::class)->weightsVersion();
            foreach ([
                "match:score:{$u1}:{$u2}", "match:score:{$u2}:{$u1}",
                "match:score:v{$version}:{$u1}:{$u2}",
            ] as $key) {
                Cache::forget($key);
            }
            foreach ([$a->id, $b->id] as $uid) {
                Cache::forget('discovery:picks:'.today()->toDateString().':'.$uid);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Removing your like always ends mutuality: a one-sided like can never
     * sustain a match, so any active match for the pair is deactivated.
     */
    protected function deactivateActiveMatch(User $a, User $b): void
    {
        [$u1, $u2] = UserMatch::canonical((int) $a->id, (int) $b->id);
        UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->where('is_active', true)
            ->update(['is_active' => false, 'unmatched_at' => now()]);
    }

    /** Monthly super-like quota from the sender's plan; free members get a small daily quota. */
    protected function enforceSuperLikeLimit(User $sender): void
    {
        if ($sender->isPremium()) {
            $quota = (int) (app(MembershipService::class)->currentFeatures($sender)['monthly_super_likes'] ?? 0);
            if ($quota <= 0) {
                return; // premium without explicit quota: unlimited
            }
            $used = SuperLike::where('sender_id', $sender->id)
                ->where('created_at', '>=', now()->startOfMonth())->count();
            if ($used >= $quota) {
                throw new \RuntimeException('Monthly super like quota reached.');
            }

            return;
        }
        $limit = (int) config('jodohku.limits.free_daily_super_likes', 1);
        $used = SuperLike::where('sender_id', $sender->id)->whereDate('created_at', today())->count();
        if ($used >= $limit) {
            throw new \RuntimeException('Daily super like limit reached. Upgrade to Premium for more.');
        }
    }

    public function pass(User $user, User $target): bool
    {
        $this->guard($user, $target);

        return DB::transaction(function () use ($user, $target) {
            $hadLike = Like::where('liker_id', $user->id)->where('liked_id', $target->id)->delete() > 0;
            if ($hadLike) {
                $this->deactivateActiveMatch($user, $target);
            }
            Rewind::create([
                'user_id' => $user->id,
                'target_type' => User::class,
                'target_id' => $target->id,
                'undone_at' => null,
            ]);

            return true;
        });
    }

    public function superLike(User $sender, User $receiver, ?string $message = null): array
    {
        $this->guard($sender, $receiver);
        $this->enforceSuperLikeLimit($sender);

        return DB::transaction(function () use ($sender, $receiver, $message) {
            $sl = SuperLike::firstOrCreate(
                ['sender_id' => $sender->id, 'receiver_id' => $receiver->id, 'used_at' => now()->startOfDay()],
                ['message' => $message, 'used_at' => now()]
            );
            $result = $this->like($sender, $receiver, true);
            $result['super_like'] = $sl->fresh();

            return $result;
        });
    }

    public function favorite(User $user, User $target): Favorite
    {
        $this->guard($user, $target);

        return DB::transaction(fn () => Favorite::firstOrCreate(
            ['user_id' => $user->id, 'favorited_id' => $target->id]
        ));
    }

    public function unfavorite(User $user, User $target): bool
    {
        return (bool) Favorite::where('user_id', $user->id)->where('favorited_id', $target->id)->delete();
    }

    /** Plans may grant unlimited rewind via the `unlimited_rewind` feature flag. */
    protected function hasUnlimitedRewind(User $user): bool
    {
        if (! $user->isPremium()) {
            return false;
        }
        $features = app(MembershipService::class)->currentFeatures($user)['features'] ?? [];

        return (bool) ($features['unlimited_rewind'] ?? false);
    }

    /** Undo the last pass/like action. */
    public function rewind(User $user): ?array
    {
        $cooldown = (int) config('jodohku.limits.rewind_cooldown_minutes', 5);
        if ($cooldown > 0 && ! $this->hasUnlimitedRewind($user)) {
            $lastUndoAt = Rewind::where('user_id', $user->id)->whereNotNull('undone_at')->max('undone_at');
            if ($lastUndoAt && now()->diffInMinutes($lastUndoAt) < $cooldown) {
                throw new \RuntimeException('Rewind is on cooldown. Try again in a few minutes.');
            }
        }

        return DB::transaction(function () use ($user) {
            $last = Rewind::where('user_id', $user->id)->whereNull('undone_at')->latest('id')->first();
            if (! $last) {
                // fallback: remove most recent like
                $like = Like::where('liker_id', $user->id)->latest('id')->first();
                if (! $like) {
                    return null;
                }
                $target = User::find($like->liked_id);
                $targetId = $like->liked_id;
                $like->delete();
                if ($target) {
                    $this->deactivateActiveMatch($user, $target);
                }
                Rewind::create(['user_id' => $user->id, 'target_type' => Like::class, 'target_id' => $like->id, 'undone_at' => now()]);

                return ['undone' => 'like', 'target_id' => $targetId];
            }
            $last->update(['undone_at' => now()]);

            return ['undone' => 'pass', 'target_id' => $last->target_id];
        });
    }
}
