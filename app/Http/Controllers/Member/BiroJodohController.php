<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CompatibilityReport;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Counselor;
use App\Models\Courtship;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\AiChatAssistantService;
use App\Services\CompatibilityReportService;
use App\Services\ConsultationService;
use App\Services\CourtshipService;
use App\Services\MarriageJourneyService;
use App\Services\SuccessStoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BiroJodohController extends Controller
{
    // ---- Courtship (tahapan taaruf) ----

    protected function html(Request $request, $data, string $view, array $extra = [])
    {
        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view($view, array_merge(['data' => $data], $extra));
    }

    protected function mutate(Request $request, $data, string $message, int $code = 200)
    {
        if ($request->wantsJson()) {
            return response()->json($data, $code);
        }

        return back()->with('status', $message);
    }

    protected function fail(Request $request, string $message, int $code = 422)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], $code);
        }

        return back()->withErrors(['action' => $message]);
    }

    public function courtships(Request $request, CourtshipService $courtships)
    {
        $items = $courtships->forUser($request->user(), (int) $request->query('per_page', 20));

        return $this->html($request, $items, 'member.biro-jodoh.courtships');
    }

    public function startCourtship(Request $request, CourtshipService $courtships)
    {
        $request->validate([
            'partner_id' => ['required', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_relation' => ['nullable', 'string', 'max:60'],
        ]);
        $partner = User::findOrFail($request->integer('partner_id'));
        $this->authorize('view', $partner);
        $conversationId = $request->input('conversation_id');
        if ($conversationId) {
            $conv = Conversation::findOrFail($conversationId);
            abort_unless($conv->involves($request->user()->id) && $conv->involves($partner->id), 422, 'Conversation must involve both parties.');
        }
        try {
            $courtship = $courtships->start($request->user(), $partner, $request->only(['conversation_id', 'guardian_name', 'guardian_phone', 'guardian_relation']));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $courtship->load(['initiator', 'partner']), 'Taaruf dimulai. Semoga dimudahkan.', 201);
    }

    public function showCourtship(Request $request, Courtship $courtship, MarriageJourneyService $journey)
    {
        $this->authorize('view', $courtship);
        $courtship->load(['initiator', 'partner', 'match', 'conversation']);
        $other = (int) $courtship->initiator_id === (int) $request->user()->id ? $courtship->partner : $courtship->initiator;

        return $this->html($request, $courtship, 'member.biro-jodoh.courtship-show', [
            'journey' => $other ? $journey->journey($request->user(), $other) : null,
        ]);
    }

    /** On-demand conversation summary for the taaruf journey (AI with fallback). */
    public function digest(Request $request, Courtship $courtship, AiChatAssistantService $assistant)
    {
        $this->authorize('view', $courtship);
        $conversation = $courtship->conversation;
        abort_unless($conversation, 404, 'Belum ada percakapan.');

        return response()->json(['digest' => $assistant->digest($conversation, $request->user())]);
    }

    public function journey(Request $request, User $partner, MarriageJourneyService $journey)
    {
        $this->authorize('view', $partner);
        $me = $request->user();
        abort_unless((int) $me->id === (int) $partner->id || $me->isStaff()
            || Courtship::where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('initiator_id', $me->id)->where('partner_id', $partner->id))
                ->orWhere(fn ($qq) => $qq->where('initiator_id', $partner->id)->where('partner_id', $me->id)))
                ->exists()
            || UserMatch::where(fn ($q) => $q->where('user_a_id', $me->id)->orWhere('user_b_id', $me->id))
                ->where(fn ($q) => $q->where('user_a_id', $partner->id)->orWhere('user_b_id', $partner->id))
                ->exists(), 403);

        return response()->json($journey->journey($me, $partner));
    }

    public function advanceCourtship(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        try {
            $advanced = $courtships->advance($courtship, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $advanced, 'Tahap taaruf diperbarui ke '.$advanced->stage->label().'.');
    }

    public function withdrawCourtship(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        try {
            $withdrawn = $courtships->withdraw($courtship, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $withdrawn, 'Taaruf diakhiri dengan baik.');
    }

    public function setGuardian(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        $request->validate([
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_relation' => ['nullable', 'string', 'max:60'],
        ]);
        try {
            $updated = $courtships->setGuardian($courtship, $request->user(), $request->only(['guardian_name', 'guardian_phone', 'guardian_relation']));
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $updated, 'Data wali disimpan.');
    }

    public function approveGuardian(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        try {
            $approved = $courtships->approveGuardian($courtship, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $approved, 'Restu wali dicatat.');
    }

    public function addChaperone(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $guardian = User::findOrFail($request->integer('user_id'));
        try {
            $withChaperone = $courtships->addChaperone($courtship, $guardian, $request->user());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $withChaperone, 'Wali ditambahkan sebagai pendamping.', 201);
    }

    public function removeChaperone(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $guardian = User::findOrFail($request->integer('user_id'));
        try {
            $removed = $courtships->removeChaperone($courtship, $guardian, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $removed, 'Pendamping dihapus.');
    }

    // ---- Counselor consultations ----

    public function counselors(Request $request, ConsultationService $consultations)
    {
        $items = $consultations->counselors((int) $request->query('per_page', 20));

        return $this->html($request, $items, 'member.biro-jodoh.counselors');
    }

    public function bookConsultation(Request $request, ConsultationService $consultations)
    {
        $request->validate([
            'counselor_id' => ['required', 'integer', 'exists:counselors,id'],
            'topic' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:180'],
            'share_report' => ['nullable', 'boolean'],
            'shared_report_id' => ['nullable', 'integer', 'exists:compatibility_reports,id'],
        ]);
        $counselor = Counselor::findOrFail($request->integer('counselor_id'));
        try {
            $consultation = $consultations->book($request->user(), $counselor, $request->only(['topic', 'notes', 'scheduled_at', 'duration_minutes', 'share_report', 'shared_report_id']));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $consultation->load(['counselor.user']), 'Konsultasi berhasil dibooking.', 201);
    }

    public function counselorBookings(Request $request, ConsultationService $consultations)
    {
        try {
            return response()->json($consultations->forCounselorUser($request->user(), (int) $request->query('per_page', 20)));
        } catch (ModelNotFoundException) {
            abort(403, 'Counselor account required.');
        }
    }

    public function consultations(Request $request, ConsultationService $consultations)
    {
        $items = $consultations->forUser($request->user(), (int) $request->query('per_page', 20));

        return $this->html($request, $items, 'member.biro-jodoh.consultations');
    }

    public function confirmConsultation(Request $request, Consultation $consultation, ConsultationService $consultations)
    {
        $this->authorize('manage', $consultation);
        abort_unless((int) $consultation->counselor?->user_id === (int) $request->user()->id || $request->user()->isStaff(), 403);
        try {
            $confirmed = $consultations->confirm($consultation, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $confirmed, 'Konsultasi dikonfirmasi.');
    }

    public function completeConsultation(Request $request, Consultation $consultation, ConsultationService $consultations)
    {
        $this->authorize('manage', $consultation);
        abort_unless((int) $consultation->counselor?->user_id === (int) $request->user()->id || $request->user()->isStaff(), 403);
        try {
            $completed = $consultations->complete($consultation, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $completed, 'Konsultasi selesai.');
    }

    public function cancelConsultation(Request $request, Consultation $consultation, ConsultationService $consultations)
    {
        $this->authorize('manage', $consultation);
        try {
            $cancelled = $consultations->cancel($consultation, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $cancelled, 'Konsultasi dibatalkan.');
    }

    // ---- Compatibility reports ----

    public function reports(Request $request, CompatibilityReportService $reports)
    {
        $items = $reports->forUser($request->user(), (int) $request->query('per_page', 20));

        return $this->html($request, $items, 'member.biro-jodoh.reports');
    }

    public function generateReport(Request $request, CompatibilityReportService $reports)
    {
        $request->validate(['candidate_id' => ['required', 'integer', 'exists:users,id']]);
        $candidate = User::findOrFail($request->integer('candidate_id'));
        $this->authorize('view', $candidate);
        try {
            $report = $reports->generate($request->user(), $candidate);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->fail($request, $e->getMessage());
        }

        return $this->mutate($request, $report->load('candidate'), 'Laporan kecocokan dibuat.', 201);
    }

    public function showReport(Request $request, CompatibilityReport $report)
    {
        $this->authorize('view', $report);

        return $this->html($request, $report->load('candidate'), 'member.biro-jodoh.report-show');
    }

    // ---- Success stories ----

    public function stories(Request $request, SuccessStoryService $stories)
    {
        $items = $stories->published((int) $request->query('per_page', 20));

        return $this->html($request, $items, 'member.biro-jodoh.stories');
    }

    public function submitStory(Request $request, SuccessStoryService $stories)
    {
        $request->validate([
            'partner_name' => ['required', 'string', 'max:120'],
            'story' => ['required', 'string', 'min:50', 'max:5000'],
        ]);

        return $this->mutate($request, $stories->submit($request->user(), $request->only(['partner_name', 'story'])), 'Kisah terkirim, menunggu moderasi.', 201);
    }

    public function myStories(Request $request, SuccessStoryService $stories)
    {
        $items = $stories->mine($request->user(), (int) $request->query('per_page', 20));

        return $this->html($request, $items, 'member.biro-jodoh.stories-mine');
    }
}
