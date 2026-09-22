<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\CompatibilityReport;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Counselor;
use App\Models\Courtship;
use App\Models\User;
use App\Services\CompatibilityReportService;
use App\Services\ConsultationService;
use App\Services\CourtshipService;
use App\Services\SuccessStoryService;
use Illuminate\Http\Request;

class BiroJodohController extends Controller
{
    // ---- Courtship (tahapan taaruf) ----

    public function courtships(Request $request, CourtshipService $courtships)
    {
        return response()->json($courtships->forUser($request->user(), (int) $request->query('per_page', 20)));
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($courtship->load(['initiator', 'partner']), 201);
    }

    public function showCourtship(Request $request, Courtship $courtship)
    {
        $this->authorize('view', $courtship);

        return response()->json($courtship->load(['initiator', 'partner', 'match', 'conversation']));
    }

    public function advanceCourtship(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        try {
            return response()->json($courtships->advance($courtship, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function withdrawCourtship(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        try {
            return response()->json($courtships->withdraw($courtship, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
            return response()->json($courtships->setGuardian($courtship, $request->user(), $request->only(['guardian_name', 'guardian_phone', 'guardian_relation'])));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approveGuardian(Request $request, Courtship $courtship, CourtshipService $courtships)
    {
        $this->authorize('manage', $courtship);
        try {
            return response()->json($courtships->approveGuardian($courtship, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ---- Counselor consultations ----

    public function counselors(Request $request, ConsultationService $consultations)
    {
        return response()->json($consultations->counselors((int) $request->query('per_page', 20)));
    }

    public function bookConsultation(Request $request, ConsultationService $consultations)
    {
        $request->validate([
            'counselor_id' => ['required', 'integer', 'exists:counselors,id'],
            'topic' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:180'],
        ]);
        $counselor = Counselor::findOrFail($request->integer('counselor_id'));
        try {
            $consultation = $consultations->book($request->user(), $counselor, $request->only(['topic', 'notes', 'scheduled_at', 'duration_minutes']));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($consultation->load(['counselor.user']), 201);
    }

    public function consultations(Request $request, ConsultationService $consultations)
    {
        return response()->json($consultations->forUser($request->user(), (int) $request->query('per_page', 20)));
    }

    public function confirmConsultation(Request $request, Consultation $consultation, ConsultationService $consultations)
    {
        $this->authorize('manage', $consultation);
        abort_unless((int) $consultation->counselor?->user_id === (int) $request->user()->id || $request->user()->isStaff(), 403);
        try {
            return response()->json($consultations->confirm($consultation, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function completeConsultation(Request $request, Consultation $consultation, ConsultationService $consultations)
    {
        $this->authorize('manage', $consultation);
        abort_unless((int) $consultation->counselor?->user_id === (int) $request->user()->id || $request->user()->isStaff(), 403);
        try {
            return response()->json($consultations->complete($consultation, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancelConsultation(Request $request, Consultation $consultation, ConsultationService $consultations)
    {
        $this->authorize('manage', $consultation);
        try {
            return response()->json($consultations->cancel($consultation, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ---- Compatibility reports ----

    public function reports(Request $request, CompatibilityReportService $reports)
    {
        return response()->json($reports->forUser($request->user(), (int) $request->query('per_page', 20)));
    }

    public function generateReport(Request $request, CompatibilityReportService $reports)
    {
        $request->validate(['candidate_id' => ['required', 'integer', 'exists:users,id']]);
        $candidate = User::findOrFail($request->integer('candidate_id'));
        $this->authorize('view', $candidate);
        try {
            $report = $reports->generate($request->user(), $candidate);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($report->load('candidate'), 201);
    }

    public function showReport(Request $request, CompatibilityReport $report)
    {
        $this->authorize('view', $report);

        return response()->json($report->load('candidate'));
    }

    // ---- Success stories ----

    public function stories(Request $request, SuccessStoryService $stories)
    {
        return response()->json($stories->published((int) $request->query('per_page', 20)));
    }

    public function submitStory(Request $request, SuccessStoryService $stories)
    {
        $request->validate([
            'partner_name' => ['required', 'string', 'max:120'],
            'story' => ['required', 'string', 'min:50', 'max:5000'],
        ]);

        return response()->json($stories->submit($request->user(), $request->only(['partner_name', 'story'])), 201);
    }

    public function myStories(Request $request, SuccessStoryService $stories)
    {
        return response()->json($stories->mine($request->user(), (int) $request->query('per_page', 20)));
    }
}
