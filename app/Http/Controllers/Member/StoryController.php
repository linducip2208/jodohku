<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMediaUpload;
use App\Models\Report;
use App\Models\Story;
use App\Models\StoryView;
use App\Notifications\StoryReacted;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\ReactionService;
use App\Services\StoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index(Request $request, StoryService $stories)
    {
        $tray = $stories->tray($request->user(), 30);

        return $request->wantsJson()
            ? response()->json($tray)
            : view('member.stories.index', ['tray' => $tray]);
    }

    public function store(Request $request, StoryService $stories)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:text,image,video'],
            'body' => ['nullable', 'string', 'max:500'],
            'media' => ['nullable', 'file', 'max:32768', 'mimes:jpg,jpeg,png,webp,mp4,webm'],
            'visibility' => ['nullable', 'string', 'in:public,members_only,matches_only'],
            'ttl_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
        ]);
        if (($data['type'] ?? 'text') === 'text' && trim((string) ($data['body'] ?? '')) === '') {
            return $request->wantsJson()
                ? response()->json(['message' => 'Text story needs a body.'], 422)
                : back()->withErrors(['body' => 'Isi cerita teks dulu.']);
        }
        if (in_array($data['type'] ?? '', ['image', 'video'], true) && ! $request->hasFile('media')) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Media file required.'], 422)
                : back()->withErrors(['media' => 'File media wajib.']);
        }

        $mediaPath = null;
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('stories/'.$request->user()->id, 'public');
        }
        $story = $stories->create($request->user(), [
            'type' => $data['type'], 'media_path' => $mediaPath,
            'body' => $data['body'] ?? null, 'visibility' => $data['visibility'] ?? 'public',
            'ttl_hours' => $data['ttl_hours'] ?? Story::DEFAULT_TTL_HOURS,
        ]);
        if ($mediaPath && ($data['type'] ?? '') === 'image') {
            ProcessMediaUpload::dispatch($story->id);
        }

        return $request->wantsJson()
            ? response()->json($story->fresh(), 201)
            : back()->with('status', 'Story terkirim (24 jam).');
    }

    public function show(Request $request, Story $story, StoryService $stories)
    {
        $this->authorize('view', $story);
        $stories->markViewed($request->user(), $story);
        $viewers = $request->user()->isPremium() || (int) $request->user()->id === (int) $story->user_id || $request->user()->isStaff()
            ? StoryView::with('user:id,display_name,name')->where('story_id', $story->id)->latest('id')->limit(50)->get()
            : collect();

        return $request->wantsJson()
            ? response()->json(['story' => $story->fresh(), 'viewed' => true, 'viewers' => $viewers, 'viewers_count' => $story->views_count])
            : view('member.stories.show', ['story' => $story->fresh(), 'viewers' => $viewers]);
    }

    public function react(Request $request, Story $story, ReactionService $reactions, NotificationService $notifications)
    {
        $this->authorize('view', $story);
        $data = $request->validate(['type' => ['nullable', 'string', 'max:20']]);
        $result = $reactions->toggleStory($request->user(), $story, (string) ($data['type'] ?? 'like'));
        if ($result['active'] && (int) $story->user_id !== (int) $request->user()->id) {
            try {
                $notifications->send($story->user, new StoryReacted($request->user(), $story, $result['type']));
            } catch (\Throwable) {
            }
        }

        return $request->wantsJson()
            ? response()->json($result)
            : back();
    }

    public function destroy(Request $request, Story $story)
    {
        $this->authorize('delete', $story);
        if ($story->media_path) {
            Storage::disk('public')->delete($story->media_path);
        }
        $story->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Story deleted.'])
            : back()->with('status', 'Story dihapus.');
    }

    public function report(Request $request, Story $story, AuditService $audit)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:50']]);
        Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $story->user_id,
            'reportable_type' => Story::class,
            'reportable_id' => $story->id,
            'reason' => $data['reason'] ?? 'other',
            'details' => 'Laporan story #'.$story->id,
            'status' => 'pending',
        ]);
        try {
            $audit->log('story.reported', $request->user(), $story);
        } catch (\Throwable) {
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Laporan terkirim.'])
            : back()->with('status', 'Laporan terkirim. Tim moderasi meninjau.');
    }
}
