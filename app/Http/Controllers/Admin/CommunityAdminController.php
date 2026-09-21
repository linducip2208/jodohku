<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Services\AuditService;
use Illuminate\Http\Request;

class CommunityAdminController extends Controller
{
    public function posts(Request $request)
    {
        return response()->json(Post::with('user')->latest('id')->paginate(25));
    }

    public function moderatePost(Request $request, Post $post, AuditService $audit)
    {
        $request->validate(['action' => ['required', 'string', 'in:hide,unhide,delete']]);
        $action = $request->string('action')->toString();
        if ($action === 'delete') {
            $post->delete();
        } else {
            $post->update(['is_hidden' => $action === 'hide']);
        }
        $audit->log('admin.post.moderated', $request->user(), $post, [], ['action' => $action]);

        return response()->json(['message' => 'Done.']);
    }

    public function groups(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate(['name' => ['required', 'string', 'max:160']]);
            $g = Group::create($request->only(['name', 'description', 'cover_path']));

            return response()->json($g, 201);
        }

        return response()->json(Group::orderByDesc('id')->paginate(25));
    }

    public function events(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate(['title' => ['required', 'string', 'max:190'], 'starts_at' => ['required', 'date']]);
            $e = Event::create($request->all());

            return response()->json($e, 201);
        }

        return response()->json(Event::orderBy('starts_at')->paginate(25));
    }

    public function updateEvent(Request $request, Event $event, AuditService $audit)
    {
        $event->update($request->all());
        $audit->log('admin.event.updated', $request->user(), $event);

        return response()->json($event->fresh());
    }

    public function blogs(Request $request)
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:220'],
                'excerpt' => ['nullable', 'string', 'max:500'],
                'body' => ['required', 'string'],
                'status' => ['required', 'string', 'in:draft,published'],
                'published_at' => ['nullable', 'date'],
            ]);
            $post = \App\Models\BlogPost::create($data + ['user_id' => $request->user()->id]);
            app(AuditService::class)->log('admin.blog.created', $request->user(), $post);

            return response()->json($post, 201);
        }

        return response()->json(\App\Models\BlogPost::with('author')->latest('id')->paginate(25));
    }

    public function updateBlog(Request $request, \App\Models\BlogPost $blog, AuditService $audit)
    {
        $before = $blog->only(['title', 'status']);
        $blog->update($request->validate([
            'title' => ['sometimes', 'string', 'max:220'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
        ]));
        $audit->log('admin.blog.updated', $request->user(), $blog, $before, []);

        return response()->json($blog->fresh());
    }

    public function destroyBlog(Request $request, \App\Models\BlogPost $blog, AuditService $audit)
    {
        $blog->delete();
        $audit->log('admin.blog.deleted', $request->user(), $blog);

        return response()->json(['message' => 'Deleted.']);
    }

    public function forums(Request $request)
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'slug' => ['required', 'string', 'max:190', 'unique:forums,slug'],
                'description' => ['nullable', 'string'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]);
            $forum = \App\Models\Forum::create($data);

            return response()->json($forum, 201);
        }

        return response()->json(\App\Models\Forum::withCount('threads')->orderBy('sort_order')->paginate(25));
    }

    public function moderateThread(Request $request, \App\Models\ForumThread $thread, AuditService $audit)
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:hide,unhide,lock,unlock,pin,unpin,delete'],
        ]);
        $action = $data['action'];
        match ($action) {
            'hide' => $thread->update(['is_hidden' => true]),
            'unhide' => $thread->update(['is_hidden' => false]),
            'lock' => $thread->update(['is_locked' => true]),
            'unlock' => $thread->update(['is_locked' => false]),
            'pin' => $thread->update(['is_pinned' => true]),
            'unpin' => $thread->update(['is_pinned' => false]),
            'delete' => $thread->delete(),
        };
        $audit->log('admin.thread.moderated', $request->user(), $thread, [], ['action' => $action]);

        return response()->json(['message' => 'Done.']);
    }

    public function moderateReply(Request $request, \App\Models\ForumReply $reply, AuditService $audit)
    {
        $request->validate(['action' => ['required', 'string', 'in:hide,unhide,delete']]);
        $action = $request->string('action')->toString();
        if ($action === 'delete') {
            $reply->delete();
        } else {
            $reply->update(['is_hidden' => $action === 'hide']);
        }
        $audit->log('admin.reply.moderated', $request->user(), $reply, [], ['action' => $action]);

        return response()->json(['message' => 'Done.']);
    }
}
