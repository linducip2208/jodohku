<?php

namespace App\Http\Controllers\Member;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'string', 'in:published,ongoing'], 'city' => ['nullable', 'string', 'max:120']]);
        $query = Event::where('status', $request->input('status', 'published'));
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

    /** Member-created events publish immediately (throttled, reportable). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:120'],
            'venue' => ['nullable', 'string', 'max:200'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:2', 'max:10000'],
            'is_online' => ['nullable', 'boolean'],
            'online_url' => ['nullable', 'string', 'max:500'],
        ]);
        $event = Event::create([
            'host_id' => $request->user()->id,
            'title' => trim($data['title']),
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(5)),
            'description' => $data['description'] ?? null,
            'city' => $data['city'] ?? $request->user()->city,
            'venue' => $data['venue'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_online' => (bool) ($data['is_online'] ?? false),
            'online_url' => $data['online_url'] ?? null,
            'status' => EventStatus::Published,
        ]);

        return $request->wantsJson()
            ? response()->json($event->fresh(), 201)
            : redirect('/events/'.$event->id)->with('status', 'Event dibuat.');
    }

    public function join(Request $request, Event $event)
    {
        $existing = $event->members()->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Already joined.'], 422);
        }
        if (! $event->status->isOpenForJoin()) {
            return response()->json(['message' => 'Event is not open for join.'], 422);
        }
        if ($event->seatsLeft() === 0) {
            return response()->json(['message' => 'Event is full.'], 422);
        }
        $event->members()->create(['user_id' => $request->user()->id, 'status' => 'confirmed']);

        return response()->json(['message' => 'Joined event.'], 201);
    }

    public function leave(Request $request, Event $event)
    {
        $event->members()->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Left event.']);
    }

    public function rsvp(Request $request, Event $event, ?string $status = null)
    {
        $status = $status ?? $request->input('status', 'confirmed');
        $request->merge(['status' => $status]);
        $request->validate(['status' => ['required', 'string', 'in:confirmed,declined,maybe']]);
        $status = $request->string('status')->toString();
        $member = $event->members()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['status' => $status]
        );
        try {
            app(AnalyticsService::class)->capture($request->user(), 'event_rsvp', $event, ['value' => $status]);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json(['message' => "RSVP: {$status}", 'member_id' => $member->id])
            : back()->with('status', 'RSVP tersimpan: '.$status.'.');
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
        $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => ['required', 'numeric', 'between:-180,180'], 'radius' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $lat = (float) $request->input('latitude');
        $lon = (float) $request->input('longitude');
        $radius = (int) ($request->input('radius', 25));

        // Portable geo search (works on MySQL 8 and SQLite): coarse bounding
        // box in SQL, exact haversine + sort in PHP.
        $latDelta = $radius / 111.0;
        $lonDelta = $radius / (111.0 * max(cos(deg2rad($lat)), 0.01));
        $candidates = Event::where('status', 'published')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lon - $lonDelta, $lon + $lonDelta])
            ->get();
        $near = $candidates
            ->map(fn (Event $e) => ['event' => $e, 'distance_km' => $this->haversineKm($lat, $lon, (float) $e->latitude, (float) $e->longitude)])
            ->filter(fn ($row) => $row['distance_km'] <= $radius)
            ->sortBy('distance_km')->values();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $paged = new LengthAwarePaginator(
            $near->forPage($page, $perPage)->values()->map(fn ($row) => $row['event']->setAttribute('distance_km', round($row['distance_km'], 2))),
            $near->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paged);
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * 6371 * asin(min(1, sqrt($a)));
    }
}
