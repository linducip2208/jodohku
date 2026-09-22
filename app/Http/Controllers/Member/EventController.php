<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'string', 'in:published,ongoing,upcoming'], 'city' => ['nullable', 'string', 'max:120']]);
        $query = Event::where('status', 'published');
        if ($request->filled('city')) {
            $query->where('city', $request->string('city'));
        }
        $events = $query->orderBy('starts_at')->paginate(20);

        return $request->wantsJson()
            ? response()->json($events)
            : view('member.events.index', ['events' => $events]);
    }

    public function show(Request $request, Event $event)
    {
        return $request->wantsJson()
            ? response()->json($event->load('members'))
            : view('member.events.show', ['event' => $event]);
    }

    public function join(Request $request, Event $event)
    {
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        $existing = $event->members()->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Already joined.'], 422);
        }
        $event->members()->create(['user_id' => $request->user()->id, 'status' => 'confirmed', 'message' => $request->input('message')]);
        $event->increment('members_count', 1);

        return response()->json(['message' => 'Joined event.'], 201);
    }

    public function leave(Request $request, Event $event)
    {
        $deleted = $event->members()->where('user_id', $request->user()->id)->delete();
        if ($deleted) {
            $event->decrement('members_count', 1);
        }

        return response()->json(['message' => 'Left event.']);
    }

    public function rsvp(Request $request, Event $event, string $status)
    {
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        $validStatuses = ['confirmed', 'declined', 'maybe'];
        if (! in_array($status, $validStatuses)) {
            abort(422);
        }
        $member = $event->members()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['status' => $status, 'message' => $request->input('message')]
        );

        return response()->json(['message' => "RSVP: {$status}", 'member_id' => $member->id]);
    }

    public function attendees(Request $request, Event $event)
    {
        $attendees = $event->members()->with('user')->where('status', 'confirmed')->paginate(20);

        return response()->json($attendees);
    }

    public function upcoming(Request $request)
    {
        $events = Event::where('status', 'published')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')->limit(20)->get();

        return response()->json($events);
    }

    public function mine(Request $request)
    {
        $events = Event::whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id))
            ->withCount(['members'])->orderBy('starts_at')->paginate(20);

        return response()->json($events);
    }

    public function nearby(Request $request)
    {
        $request->validate(['latitude' => 'required|numeric', 'longitude' => 'required|numeric', 'radius' => 'nullable|integer|min:1|max:100']);
        $lat = (float) $request->input('latitude');
        $lon = (float) $request->input('longitude');
        $radius = (int) ($request->input('radius', 25));
        $events = Event::where('status', 'published')
            ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?", [$lat, $lon, $lat, $radius])
            ->orderByRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))", [$lat, $lon, $lat])
            ->paginate(20);

        return response()->json($events);
    }
}
