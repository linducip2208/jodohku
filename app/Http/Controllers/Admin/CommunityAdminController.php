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
}
