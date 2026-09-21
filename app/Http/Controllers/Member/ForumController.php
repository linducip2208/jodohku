<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Forum;
use App\Models\ForumThread;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        $forums = Forum::where('is_active', true)->orderBy('sort_order')
            ->withCount(['visibleThreads'])->get();

        return $request->wantsJson()
            ? response()->json($forums)
            : view('member.forums.index', ['forums' => $forums]);
    }

    public function threads(Request $request, string $slug)
    {
        $forum = Forum::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $threads = $forum->visibleThreads()->with('user')
            ->orderByDesc('is_pinned')->orderByDesc('last_reply_at')->paginate(20);

        return $request->wantsJson()
            ? response()->json($threads)
            : view('member.forums.threads', ['forum' => $forum, 'threads' => $threads]);
    }

    public function storeThread(Request $request, string $slug)
    {
        $forum = Forum::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
        $thread = $forum->threads()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'last_reply_at' => now(),
        ]);

        return response()->json($thread, 201);
    }

    public function show(Request $request, int $thread)
    {
        $t = ForumThread::with(['forum', 'user', 'visibleReplies.user'])
            ->where('is_hidden', false)->findOrFail($thread);
        $replies = $t->visibleReplies()->latest('id')->paginate(20);

        return $request->wantsJson()
            ? response()->json(['thread' => $t, 'replies' => $replies])
            : view('member.forums.thread', ['thread' => $t, 'replies' => $replies]);
    }

    public function reply(Request $request, int $thread)
    {
        $t = ForumThread::where('is_hidden', false)->findOrFail($thread);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        try {
            $reply = $t->addReply($request->user(), $data['body']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($reply, 201);
    }
}
