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
        $this->enforceDailyLikeLimit($liker);

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
            // Deactivate match if it was solely from these likes and now not mutual
            $reverseExists = Like::where('liker_id', $liked->id)->where('liked_id', $liker->id)->exists();
            if (! $reverseExists) {
                [$a, $b] = UserMatch::canonical((int) $liker->id, (int) $liked->id);
                UserMatch::where('user_a_id', $a)->where('user_b_id', $b)->where('is_active', true)
                    ->update(['is_active' => false, 'unmatched_at' => now()]);
            }

            return true;
        });
    }

    public function pass(User $user, User $target): bool
    {
        $this->guard($user, $target);

        return DB::transaction(function () use ($user, $target) {
            Like::where('liker_id', $user->id)->where('liked_id', $target->id)->delete();
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

        return DB::transaction(function () use ($sender, $receiver, $message) {
            $sl = SuperLike::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'message' => $message,
                'used_at' => now(),
            ]);
            $result = $this->like($sender, $receiver, true);
            $result['super_like'] = $sl;

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

    /** Undo the last pass/like action. */
    public function rewind(User $user): ?array
    {
        return DB::transaction(function () use ($user) {
            $last = Rewind::where('user_id', $user->id)->whereNull('undone_at')->latest('id')->first();
            if (! $last) {
                // fallback: remove most recent like
                $like = Like::where('liker_id', $user->id)->latest('id')->first();
                if (! $like) {
                    return null;
                }
                $targetId = $like->liked_id;
                $like->delete();
                Rewind::create(['user_id' => $user->id, 'target_type' => Like::class, 'target_id' => $like->id, 'undone_at' => now()]);

                return ['undone' => 'like', 'target_id' => $targetId];
            }
            $last->update(['undone_at' => now()]);

            return ['undone' => 'pass', 'target_id' => $last->target_id];
        });
    }
}
