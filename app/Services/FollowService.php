<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Follow;
use App\Models\GroupMember;
use App\Models\Mute;
use App\Models\User;
use App\Notifications\SocialFollow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Social graph edges. Mirrors LikeService guards (no self, no blocks);
 * mutes hide content but never block the edge itself.
 */
class FollowService
{
    public function __construct(protected AnalyticsService $analytics) {}

    protected function guard(User $a, User $b): void
    {
        if ((int) $a->id === (int) $b->id) {
            throw new \InvalidArgumentException('Cannot follow yourself.');
        }
        if (Block::existsBetween((int) $a->id, (int) $b->id)) {
            throw new \RuntimeException('Interaction blocked.');
        }
        if (($b->status?->value ?? 'active') !== 'active' || $b->trashed()) {
            throw new \RuntimeException('Account unavailable.');
        }
    }

    public function follow(User $follower, User $followed): Follow
    {
        $this->guard($follower, $followed);

        $follow = DB::transaction(fn () => Follow::firstOrCreate(
            ['follower_id' => $follower->id, 'followed_id' => $followed->id]
        ));
        if ($follow->wasRecentlyCreated) {
            Cache::forget($this->suggestKey($follower));
            try {
                app(NotificationService::class)->send($followed, new SocialFollow($follower));
            } catch (\Throwable) {
            }
            $this->analytics->capture($follower, 'profile_follow', $followed);
        }

        return $follow;
    }

    public function unfollow(User $follower, User $followed): bool
    {
        $deleted = (bool) Follow::where('follower_id', $follower->id)->where('followed_id', $followed->id)->delete();
        if ($deleted) {
            Cache::forget($this->suggestKey($follower));
        }

        return $deleted;
    }

    public function mute(User $muter, User $muted): Mute
    {
        if ((int) $muter->id === (int) $muted->id) {
            throw new \InvalidArgumentException('Cannot mute yourself.');
        }

        return Mute::firstOrCreate(['muter_id' => $muter->id, 'muted_id' => $muted->id]);
    }

    public function unmute(User $muter, User $muted): bool
    {
        return (bool) Mute::where('muter_id', $muter->id)->where('muted_id', $muted->id)->delete();
    }

    /** IDs the user muted (feed/discover/stories/search exclusion list). */
    public function mutedIds(User $user): array
    {
        return Mute::where('muter_id', $user->id)->pluck('muted_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Suggested people from mutual interests + mutual follows + shared
     * groups + city. Never blocked/muted/inactive/incognito/counselors.
     * Cached per user/day (cheap, deterministic for the day).
     */
    public function suggested(User $user, int $limit = 10): Collection
    {
        $key = $this->suggestKey($user);

        $ids = Cache::remember($key, now()->endOfDay(), function () use ($user, $limit) {
            $blocked = Block::where('blocker_id', $user->id)->pluck('blocked_id')
                ->merge(Block::where('blocked_id', $user->id)->pluck('blocker_id'))->all();
            $excluded = array_unique(array_merge($blocked, $this->mutedIds($user), [$user->id]));

            $interestIds = $user->interests()->pluck('interests.id')->all();
            $myFollows = Follow::where('follower_id', $user->id)->pluck('followed_id')->all();
            $myGroups = GroupMember::where('user_id', $user->id)->pluck('group_id')->all();

            $candidates = User::query()->active()->real()->where('is_paused', false)->whereNotIn('users.id', $excluded ?: [0])
                ->whereDoesntHave('counselor')
                ->whereDoesntHave('profilePrivacy', fn ($q) => $q->where('is_incognito', true))
                ->whereNotIn('users.id', Follow::where('follower_id', $user->id)->select('followed_id'))
                ->with(['profile', 'interests'])
                ->limit(200)->get();

            return $candidates->map(function (User $cand) use ($user, $interestIds, $myFollows, $myGroups) {
                $score = 0;
                $reasons = [];
                $shared = $cand->interests->pluck('id')->intersect($interestIds)->count();
                if ($shared > 0) {
                    $score += $shared * 10;
                    $reasons[] = 'Memiliki beberapa minat yang sama';
                }
                $mutual = Follow::where('follower_id', $cand->id)->whereIn('followed_id', $myFollows)->count();
                if ($mutual > 0) {
                    $score += $mutual * 8;
                    $reasons[] = 'Diikuti oleh orang yang kamu ikuti';
                }
                if ($myGroups && GroupMember::where('user_id', $cand->id)->whereIn('group_id', $myGroups)->exists()) {
                    $score += 12;
                    $reasons[] = 'Aktif di komunitas yang sama';
                }
                if ($user->city && $cand->city && mb_strtolower($user->city) === mb_strtolower($cand->city)) {
                    $score += 6;
                    $reasons[] = 'Satu kota denganmu';
                }

                return ['id' => $cand->id, 'score' => $score, 'reasons' => array_values(array_unique($reasons))];
            })->filter(fn ($r) => $r['score'] > 0)
                ->sortByDesc('score')->take($limit)->values()->all();
        });

        $users = $ids ? User::whereIn('id', collect($ids)->pluck('id'))->with(['profile', 'interests'])->get()->keyBy('id') : collect();

        return collect($ids)->map(fn ($r) => ['user' => $users->get($r['id']), 'reasons' => $r['reasons']])
            ->filter(fn ($r) => $r['user'])->values();
    }

    protected function suggestKey(User $user): string
    {
        return 'social:suggest:'.today()->toDateString().':'.$user->id;
    }
}
