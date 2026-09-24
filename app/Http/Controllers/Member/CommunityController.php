<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostBookmark;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\Report;
use App\Notifications\PostCommented;
use App\Notifications\PostReacted;
use App\Services\AnalyticsService;
use App\Services\AuditService;
use App\Services\CreditService;
use App\Services\MentionService;
use App\Services\NotificationService;
use App\Services\ReactionService;
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
        try {
            app(ReactionService::class)->prime($posts->getCollection(), $request->user());
        } catch (\Throwable) {
        }

        if ($request->wantsJson()) {
            return response()->json($posts);
        }

        return view('member.community.index', ['posts' => $posts, 'likedIds' => $likedIds]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'visibility' => ['nullable', 'string', 'in:public,members_only,premium_only,matches_only'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);
        if (! empty($data['group_id'])) {
            $group = Group::findOrFail($data['group_id']);
            abort_unless($group->hasMember((int) $request->user()->id) || $request->user()->isStaff(), 403);
        }
        $post = DB::transaction(fn () => Post::create([
            'user_id' => $request->user()->id,
            'group_id' => $data['group_id'] ?? null,
            'body' => trim($data['body']),
            'visibility' => $data['visibility'] ?? 'public',
        ]));
        try {
            app(MentionService::class)->sync($post, $post->body, $request->user());
            app(AnalyticsService::class)->capture($request->user(), 'post_created', $post);
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
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);
        if (! empty($data['parent_id'])) {
            $parent = Comment::findOrFail($data['parent_id']);
            abort_unless((int) $parent->post_id === (int) $post->id, 422);
        }
        $comment = DB::transaction(function () use ($post, $request, $data) {
            $c = Comment::create([
                'post_id' => $post->id, 'user_id' => $request->user()->id,
                'parent_id' => $data['parent_id'] ?? null, 'body' => trim($data['body']),
            ]);
            $post->increment('comments_count');

            return $c;
        });
        try {
            app(MentionService::class)->sync($comment, $comment->body, $request->user());
            if ((int) $post->user_id !== (int) $request->user()->id) {
                app(NotificationService::class)->send(
                    $post->user, new PostCommented($request->user(), $comment));
            }
            app(AnalyticsService::class)->capture($request->user(), 'comment_created', $comment);
        } catch (\Throwable) {
        }

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

    public function commentUpdate(Request $request, Comment $comment)
    {
        $this->authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $comment->update(['body' => trim($data['body'])]);
        try {
            app(MentionService::class)->sync($comment, $comment->body, $request->user());
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json($comment->fresh())
            : back()->with('status', 'Komentar diperbarui.');
    }

    public function commentDestroy(Request $request, Comment $comment)
    {
        $this->authorize('delete', $comment);
        $post = $comment->post;
        DB::transaction(function () use ($comment, $post) {
            $count = 1 + Comment::where('parent_id', $comment->id)->count();
            Comment::where('parent_id', $comment->id)->delete();
            $comment->delete();
            $post?->decrement('comments_count', $count);
        });

        return $request->wantsJson()
            ? response()->json(['message' => 'Deleted.'])
            : back()->with('status', 'Komentar dihapus.');
    }

    public function commentReact(Request $request, Comment $comment, ReactionService $reactions)
    {
        abort_unless(! $comment->post?->is_hidden, 404);
        $data = $request->validate(['type' => ['nullable', 'string', 'max:20']]);

        return $request->wantsJson()
            ? response()->json($reactions->toggleComment($request->user(), $comment, (string) ($data['type'] ?? 'like')))
            : back();
    }

    public function react(Request $request, Post $post, ReactionService $reactions)
    {
        abort_unless(! $post->is_hidden, 404);
        $this->authorizePostVisible($request, $post);
        $data = $request->validate(['type' => ['nullable', 'string', 'max:20']]);
        $result = $reactions->togglePost($request->user(), $post, (string) ($data['type'] ?? 'like'));
        if ($result['active'] && (int) $post->user_id !== (int) $request->user()->id) {
            try {
                app(NotificationService::class)->send(
                    $post->user, new PostReacted($request->user(), $post, $result['type']));
            } catch (\Throwable) {
            }
        }

        return $request->wantsJson()
            ? response()->json($result)
            : back();
    }

    public function bookmark(Request $request, Post $post)
    {
        abort_unless(! $post->is_hidden, 404);
        $this->authorizePostVisible($request, $post);
        $existing = PostBookmark::where('post_id', $post->id)->where('user_id', $request->user()->id)->first();
        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            PostBookmark::create(['post_id' => $post->id, 'user_id' => $request->user()->id]);
            $saved = true;
        }
        try {
            app(AnalyticsService::class)->capture($request->user(), $saved ? 'post_saved' : 'post_unsaved', $post);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json(['saved' => $saved])
            : back();
    }

    public function bookmarks(Request $request)
    {
        $items = PostBookmark::with(['post.user:id,display_name,name,avatar_path,is_verified'])
            ->where('user_id', $request->user()->id)->latest('id')->paginate(15);

        return $request->wantsJson()
            ? response()->json($items)
            : view('member.community.bookmarks', ['items' => $items]);
    }

    public function share(Request $request, Post $post)
    {
        abort_unless(! $post->is_hidden, 404);
        $this->authorizePostVisible($request, $post);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:500']]);
        $share = DB::transaction(function () use ($post, $request, $data) {
            $s = PostShare::create([
                'post_id' => $post->id, 'user_id' => $request->user()->id,
                'body' => isset($data['body']) ? trim($data['body']) : null,
            ]);
            $post->increment('shares_count');

            return $s;
        });
        try {
            app(AnalyticsService::class)->capture($request->user(), 'post_shared', $post);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json($share->fresh(), 201)
            : back()->with('status', 'Postingan dibagikan.');
    }

    public function postUpdate(Request $request, Post $post)
    {
        $this->authorize('update', $post);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'visibility' => ['nullable', 'string', 'in:public,members_only,premium_only,matches_only'],
        ]);
        $post->update(['body' => trim($data['body']), 'visibility' => $data['visibility'] ?? $post->visibility]);
        try {
            app(MentionService::class)->sync($post, $post->body, $request->user());
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json($post->fresh())
            : back()->with('status', 'Postingan diperbarui.');
    }

    /** Paid post boost (credits): presentation priority in feeds, labeled. */
    public function boostPost(Request $request, Post $post, CreditService $credits)
    {
        abort_unless((int) $post->user_id === (int) $request->user()->id, 403);
        abort_unless(! $post->is_hidden, 404);
        $cost = max(1, (int) config('jodohku.boost.post_cost', 50));
        try {
            $credits->spend($request->user(), $cost, 'Post boost #'.$post->id, ['post_id' => $post->id]);
        } catch (\Throwable $e) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Kredit tidak cukup.'], 422)
                : back()->withErrors(['boost' => 'Kredit tidak cukup.']);
        }
        $post->update(['boosted_until' => now()->addDay()]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Post boosted for 24h.', 'boosted_until' => $post->boosted_until])
            : back()->with('status', 'Postingan dipromosikan 24 jam.');
    }

    public function reportComment(Request $request, Comment $comment, AuditService $audit)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:50']]);
        Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $comment->user_id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'reason' => $data['reason'] ?? 'other',
            'details' => 'Laporan komentar #'.$comment->id,
            'status' => 'pending',
        ]);
        try {
            $audit->log('community.comment.reported', $request->user(), $comment);
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
