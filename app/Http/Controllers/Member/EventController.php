<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::where('status', 'published')->orderBy('starts_at')->paginate(20);

        return $request->wantsJson()
            ? response()->json($events)
            : view('member.events', ['events' => $events]);
    }

    public function show(Request $request, Event $event)
    {
        return $request->wantsJson()
            ? response()->json($event->load('members'))
            : view('member.event-show', ['event' => $event]);
    }

    public function join(Request $request, Event $event)
    {
        $event->members()->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json(['message' => 'Joined event.'], 201);
    }

    public function leave(Request $request, Event $event)
    {
        $event->members()->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Left event.']);
    }
}
