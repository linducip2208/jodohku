<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Member social feed over the existing Post/Comment/PostLike models.
 * Privacy/moderation-aware: hidden or soft-deleted posts never appear,
 * posts by blocked (either direction) authors are excluded, member posts
 * beyond public require nothing extra (all member content is member-gated).
 */
class CommunityController extends Controller
{
    protected function blockedIds(int $userId): array
    {
        try {
            return Block::where('blocker_id', $userId)->pluck('blocked_id')
                ->merge(Block::where('blocked_id', $userId)->pluck('blocker_id'))
                ->map(fn ($id) => (int) $id)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function feedQuery(int $userId)
    {
        $blocked = $this->blockedIds($userId);

        return Post::where('is_hidden', false)
            ->when($blocked, fn ($q) => $q->whereNotIn('user_id', $blocked))
            ->with(['user:id,display_name,name,avatar_path,is_verified', 'comments' => fn ($q) => $q->latest('id')->limit(3)->with('user:id,display_name,name')])
            ->withCount(['comments', 'likes'])
            ->latest('id');
    }

    public function index(Request $request)
    {
        $posts = $this->feedQuery((int) $request->user()->id)->paginate(15);
        $likedIds = PostLike::where('user_id', $request->user()->id)
            ->whereIn('post_id', $posts->getCollection()->pluck('id'))->pluck('post_id')->all();

        if ($request->wantsJson()) {
            return response()->json($posts);
        }

        return view('member.community.index', ['posts' => $posts, 'likedIds' => $likedIds]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $post = DB::transaction(fn () => Post::create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'visibility' => 'public',
        ]));
        try {
            $audit->log('community.post.created', $request->user(), $post);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json($post->fresh(), 201)
            : back()->with('status', 'Postingan terkirim.');
    }

    public function toggleLike(Request $request, Post $post)
    {
        abort_unless(! $post->is_hidden, 404);
        $this->authorizePostVisible($request, $post);
        $liked = (bool) PostLike::where('post_id', $post->id)->where('user_id', $request->user()->id)->first();
        DB::transaction(function () use ($post, $request, $liked) {
            if ($liked) {
                PostLike::where('post_id', $post->id)->where('user_id', $request->user()->id)->delete();
                $post->decrement('likes_count');
            } else {
                PostLike::create(['post_id' => $post->id, 'user_id' => $request->user()->id]);
                $post->increment('likes_count');
            }
        });

        return $request->wantsJson()
            ? response()->json(['liked' => ! $liked, 'likes_count' => $post->fresh()->likes_count])
            : back();
    }

    public function comment(Request $request, Post $post)
    {
        abort_unless(! $post->is_hidden, 404);
        $this->authorizePostVisible($request, $post);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $comment = DB::transaction(function () use ($post, $request, $data) {
            $c = Comment::create(['post_id' => $post->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])]);
            $post->increment('comments_count');

            return $c;
        });

        return $request->wantsJson()
            ? response()->json($comment->fresh(), 201)
            : back()->with('status', 'Komentar terkirim.');
    }

    public function destroy(Request $request, Post $post)
    {
        abort_unless((int) $post->user_id === (int) $request->user()->id || $request->user()->isStaff(), 403);
        $post->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Deleted.'])
            : back()->with('status', 'Postingan dihapus.');
    }

    public function report(Request $request, Post $post, AuditService $audit)
    {
        abort_unless(! $post->is_hidden, 404);
        $this->authorizePostVisible($request, $post);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:50']]);
        Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $post->user_id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => $data['reason'] ?? 'other',
            'details' => 'Laporan postingan komunitas #'.$post->id,
            'status' => 'pending',
        ]);
        try {
            $audit->log('community.post.reported', $request->user(), $post);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Laporan terkirim.'])
            : back()->with('status', 'Laporan terkirim. Tim moderasi meninjau.');
    }

    protected function authorizePostVisible(Request $request, Post $post): void
    {
        $me = (int) $request->user()->id;
        abort_unless(! in_array((int) $post->user_id, $this->blockedIds($me), true), 403);
    }
}
