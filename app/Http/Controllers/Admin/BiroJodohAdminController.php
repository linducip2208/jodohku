<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Counselor;
use App\Models\Courtship;
use App\Models\SuccessStory;
use App\Services\AuditService;
use App\Services\SuccessStoryService;
use Illuminate\Http\Request;

class BiroJodohAdminController extends Controller
{
    protected function respond(Request $request, $data, string $view, string $key = 'items')
    {
        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view($view, [$key => $data]);
    }

    public function courtships(Request $request)
    {
        $items = Courtship::with(['initiator', 'partner'])->latest('id')->paginate(25);

        return $this->respond($request, $items, 'admin.biro-jodoh.courtships');
    }

    public function counselors(Request $request, AuditService $audit)
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'specialty' => ['required', 'string', 'max:120'],
                'bio' => ['nullable', 'string', 'max:2000'],
            ]);
            $counselor = Counselor::updateOrCreate(
                ['user_id' => $data['user_id']],
                ['specialty' => $data['specialty'], 'bio' => $data['bio'] ?? null, 'is_active' => true]
            );
            $audit->log('admin.counselor.saved', $request->user(), $counselor);

            return $request->wantsJson()
                ? response()->json($counselor, 201)
                : back()->with('status', 'Konselor disimpan.');
        }

        $items = Counselor::with('user')->orderBy('id')->paginate(25);

        return $this->respond($request, $items, 'admin.biro-jodoh.counselors');
    }

    public function updateCounselor(Request $request, Counselor $counselor, AuditService $audit)
    {
        $before = $counselor->only(['specialty', 'is_active']);
        $counselor->update($request->validate([
            'specialty' => ['sometimes', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]));
        $audit->log('admin.counselor.updated', $request->user(), $counselor, $before, []);

        return $request->wantsJson()
            ? response()->json($counselor->fresh())
            : back()->with('status', 'Konselor diperbarui.');
    }

    public function consultations(Request $request)
    {
        $items = Consultation::with(['counselor.user', 'user'])->latest('scheduled_at')->paginate(25);

        return $this->respond($request, $items, 'admin.biro-jodoh.consultations');
    }

    public function stories(Request $request, SuccessStoryService $stories)
    {
        $items = $stories->queue((int) $request->query('per_page', 25));

        return $this->respond($request, $items, 'admin.biro-jodoh.stories');
    }

    public function moderateStory(Request $request, SuccessStory $story, SuccessStoryService $stories, AuditService $audit)
    {
        $request->validate(['action' => ['required', 'string', 'in:publish,reject']]);
        $action = $request->string('action')->toString();
        if ($action === 'publish') {
            $stories->publish($story, $request->user());
        } else {
            $stories->reject($story, $request->user());
        }
        $audit->log('admin.success_story.moderated', $request->user(), $story, [], ['action' => $action]);

        return $request->wantsJson()
            ? response()->json($story->fresh())
            : back()->with('status', 'Kisah dimoderasi: '.$action.'.');
    }

    public function stats(Request $request)
    {
        return response()->json([
            'courtships_active' => Courtship::where('status', 'active')->count(),
            'courtships_completed' => Courtship::where('status', 'completed')->count(),
            'counselors_active' => Counselor::where('is_active', true)->count(),
            'consultations_pending' => Consultation::where('status', 'pending')->count(),
            'stories_published' => SuccessStory::where('status', 'published')->count(),
            'stories_pending' => SuccessStory::where('status', 'pending')->count(),
        ]);
    }
}
