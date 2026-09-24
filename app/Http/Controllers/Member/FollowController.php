<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\FollowService;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    protected function listVisible(User $viewer, User $owner, string $key): bool
    {
        if ((int) $viewer->id === (int) $owner->id || $viewer->isStaff()) {
            return true;
        }
        $vis = $owner->profilePrivacy?->$key;
        $vis = $vis?->value ?? (string) ($vis ?? 'public');
        if (in_array($vis, ['public', 'members_only'], true)) {
            return true;
        }
        if ($vis === 'matches_only') {
            return UserMatch::where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('user_a_id', $viewer->id)->where('user_b_id', $owner->id))
                ->orWhere(fn ($qq) => $qq->where('user_a_id', $owner->id)->where('user_b_id', $viewer->id)))
                ->where('is_active', true)->exists();
        }

        return false;
    }

    protected function followersVisible(User $viewer, User $owner): bool
    {
        return $this->listVisible($viewer, $owner, 'followers_visibility');
    }

    protected function followingVisible(User $viewer, User $owner): bool
    {
        return $this->listVisible($viewer, $owner, 'following_visibility');
    }

    public function followers(Request $request, User $user)
    {
        abort_unless($this->followersVisible($request->user(), $user), 403);
        $items = $user->followers()->with(['profile'])->paginate(20);

        return $request->wantsJson()
            ? response()->json($items)
            : view('member.social.followers', ['owner' => $user, 'items' => $items]);
    }

    public function following(Request $request, User $user)
    {
        abort_unless($this->followingVisible($request->user(), $user), 403);
        $items = $user->following()->with(['profile'])->paginate(20);

        return $request->wantsJson()
            ? response()->json($items)
            : view('member.social.following', ['owner' => $user, 'items' => $items]);
    }

    public function suggested(Request $request, FollowService $follows)
    {
        $items = $follows->suggested($request->user(), 12);

        return $request->wantsJson()
            ? response()->json($items->map(fn ($r) => ['user_id' => $r['user']->id, 'reasons' => $r['reasons']]))
            : view('member.social.suggested', ['items' => $items]);
    }

    public function store(Request $request, User $user, FollowService $follows)
    {
        try {
            $follows->follow($request->user(), $user);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['follow' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Following.'])
            : back()->with('status', 'Mengikuti '.$user->displayName().'.');
    }

    public function destroy(Request $request, User $user, FollowService $follows)
    {
        $follows->unfollow($request->user(), $user);

        return $request->wantsJson()
            ? response()->json(['message' => 'Unfollowed.'])
            : back()->with('status', 'Berhenti mengikuti.');
    }

    public function mute(Request $request, User $user, FollowService $follows)
    {
        try {
            $follows->mute($request->user(), $user);
        } catch (\InvalidArgumentException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['mute' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Muted.'])
            : back()->with('status', 'Konten disembunyikan dari feed.');
    }

    public function unmute(Request $request, User $user, FollowService $follows)
    {
        $follows->unmute($request->user(), $user);

        return $request->wantsJson()
            ? response()->json(['message' => 'Unmuted.'])
            : back()->with('status', 'Mute dibatalkan.');
    }
}
