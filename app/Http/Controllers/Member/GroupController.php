<?php

namespace App\Http\Controllers\Member;

use App\Enums\PrivacyVisibility;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\ReactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Communities are Groups (existing entity): discover, join/leave, post,
 * moderate. Roles: owner (owner_id) + admin/moderator/member rows.
 */
class GroupController extends Controller
{
    public function index(Request $request)
    {
        $mine = Group::whereIn('id', GroupMember::where('user_id', $request->user()->id)->select('group_id'))
            ->withCount('members')->get();
        $groups = Group::visibleTo($request->user())->withCount('members')
            ->orderByDesc('members_count')->paginate(15);

        return $request->wantsJson()
            ? response()->json(['mine' => $mine, 'groups' => $groups])
            : view('member.groups.index', ['mine' => $mine, 'groups' => $groups]);
    }

    public function show(Request $request, Group $group)
    {
        // Guests: only public groups exist (404 otherwise — no enumeration).
        if (! $request->user()) {
            $vis = $group->visibility instanceof PrivacyVisibility ? $group->visibility : PrivacyVisibility::tryFrom((string) $group->visibility);
            abort_unless($vis === PrivacyVisibility::Public, 404);
        } else {
            $this->authorize('view', $group);
        }
        $posts = Post::visibleTo($request->user())->where('group_id', $group->id)
            ->with(['user:id,display_name,name,avatar_path,is_verified', 'comments' => fn ($q) => $q->latest('id')->limit(2)])
            ->withCount(['comments', 'likes'])->latest('id')->paginate(15);
        $members = $group->members()->with('user:id,display_name,name,avatar_path')->latest('id')->limit(20)->get();
        try {
            app(ReactionService::class)->prime($posts->getCollection(), $request->user());
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json(['group' => $group->fresh(), 'posts' => $posts, 'members' => $members])
            : view('member.groups.show', ['group' => $group, 'posts' => $posts, 'members' => $members]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', 'string', 'in:public,members_only,private'],
            'category' => ['nullable', 'string', 'max:80'],
            'interests' => ['nullable', 'array', 'max:10'],
            'rules' => ['nullable', 'string', 'max:2000'],
        ]);
        $group = DB::transaction(function () use ($request, $data) {
            $g = Group::create([
                'owner_id' => $request->user()->id, 'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'category' => $data['category'] ?? null,
                'interests' => $data['interests'] ?? null,
                'rules' => $data['rules'] ?? null,
            ]);
            $g->members()->create(['user_id' => $request->user()->id, 'role' => 'admin', 'joined_at' => now()]);
            $g->increment('members_count');

            return $g;
        });
        try {
            Cache::forget('seo:sitemap');
            Cache::forget('seo:sitemap:groups');
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json($group->fresh(), 201)
            : redirect('/groups/'.$group->slug)->with('status', 'Komunitas dibuat.');
    }

    public function join(Request $request, Group $group)
    {
        $this->authorize('join', $group);
        DB::transaction(function () use ($request, $group) {
            GroupMember::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $request->user()->id],
                ['role' => 'member', 'joined_at' => now()]
            );
            $group->update(['members_count' => $group->members()->count()]);
        });
        try {
            app(AnalyticsService::class)->capture($request->user(), 'community_join', $group);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Joined.'])
            : back()->with('status', 'Bergabung ke '.$group->name.'.');
    }

    public function leave(Request $request, Group $group)
    {
        abort_unless((int) $group->owner_id !== (int) $request->user()->id, 422, 'Owner cannot leave; transfer ownership or delete the group.');
        GroupMember::where('group_id', $group->id)->where('user_id', $request->user()->id)->delete();
        $group->update(['members_count' => $group->members()->count()]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Left.'])
            : back()->with('status', 'Keluar dari komunitas.');
    }

    public function update(Request $request, Group $group)
    {
        $this->authorize('manage', $group);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', 'string', 'in:public,members_only,private'],
            'category' => ['nullable', 'string', 'max:80'],
            'interests' => ['nullable', 'array', 'max:10'],
            'rules' => ['nullable', 'string', 'max:2000'],
        ]);
        $group->update($data);

        return $request->wantsJson()
            ? response()->json($group->fresh())
            : back()->with('status', 'Komunitas diperbarui.');
    }

    public function manageMember(Request $request, Group $group)
    {
        $this->authorize('manage', $group);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'string', 'in:promote,demote,remove'],
        ]);
        $member = GroupMember::where('group_id', $group->id)->where('user_id', $data['user_id'])->firstOrFail();
        abort_unless((int) $data['user_id'] !== (int) $group->owner_id, 422);
        match ($data['action']) {
            'promote' => $member->update(['role' => 'moderator']),
            'demote' => $member->update(['role' => 'member']),
            'remove' => $member->delete(),
        };
        $group->update(['members_count' => $group->members()->count()]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Member updated.'])
            : back()->with('status', 'Anggota diperbarui.');
    }

    /** Manager invites a user by username (direct add + notification). */
    public function invite(Request $request, Group $group)
    {
        $this->authorize('manage', $group);
        $data = $request->validate(['username' => ['required', 'string', 'max:190']]);
        $user = User::where('username', $data['username'])->firstOrFail();
        abort_unless(! $group->hasMember((int) $user->id), 422, 'Sudah anggota.');
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now()]);
        $group->update(['members_count' => $group->members()->count()]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Invited.'])
            : back()->with('status', $user->displayName().' diundang ke grup.');
    }

    /** Cover upload (manager only, images ≤8MB, public disk). */
    public function cover(Request $request, Group $group)
    {
        $this->authorize('manage', $group);
        $data = $request->validate(['cover' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp']]);
        $file = $data['cover'];
        if (! $file->isValid() || @getimagesize($file->getRealPath()) === false) {
            abort(422, 'File bukan gambar valid.');
        }
        $path = $file->store('group-covers/'.$group->id, 'public');
        $group->update(['cover_path' => $path]);

        return $request->wantsJson()
            ? response()->json($group->fresh())
            : back()->with('status', 'Cover grup diperbarui.');
    }

    /** Request to join a private group (public groups join directly). */
    public function requestJoin(Request $request, Group $group)
    {
        abort_unless(! $group->hasMember((int) $request->user()->id), 422, 'Sudah anggota.');
        $req = GroupJoinRequest::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $request->user()->id],
            ['status' => GroupJoinRequest::STATUS_PENDING]
        );

        return $request->wantsJson()
            ? response()->json($req, 201)
            : back()->with('status', 'Permintaan bergabung terkirim, menunggu persetujuan pengelola.');
    }

    /** Approve/reject a join request (manager only). */
    public function decideRequest(Request $request, Group $group, GroupJoinRequest $joinRequest)
    {
        $this->authorize('manage', $group);
        abort_unless((int) $joinRequest->group_id === (int) $group->id, 404);
        $data = $request->validate(['action' => ['required', 'string', 'in:approve,reject']]);
        DB::transaction(function () use ($group, $joinRequest, $data) {
            $joinRequest->update(['status' => $data['action'] === 'approve'
                ? GroupJoinRequest::STATUS_APPROVED
                : GroupJoinRequest::STATUS_REJECTED]);
            if ($data['action'] === 'approve') {
                GroupMember::firstOrCreate(
                    ['group_id' => $group->id, 'user_id' => $joinRequest->user_id],
                    ['role' => 'member', 'joined_at' => now()]
                );
                $group->update(['members_count' => $group->members()->count()]);
            }
        });

        return $request->wantsJson()
            ? response()->json($joinRequest->fresh())
            : back()->with('status', 'Permintaan diproses.');
    }
}
