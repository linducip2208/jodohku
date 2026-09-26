<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\DatePlan;
use App\Models\User;
use App\Services\DatePlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DatePlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = DatePlan::where(fn ($q) => $q->where('proposer_id', $request->user()->id)->orWhere('partner_id', $request->user()->id))
            ->with(['proposer:id,display_name,name,avatar_path', 'partner:id,display_name,name,avatar_path'])
            ->latest('id')->paginate(20);

        return $request->wantsJson()
            ? response()->json($plans)
            : view('member.dates.index', ['plans' => $plans]);
    }

    public function store(Request $request, DatePlanService $dates)
    {
        $data = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'place' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $plan = $dates->propose($request->user(), User::findOrFail((int) $data['partner_id']), [
                'scheduled_at' => Carbon::parse($data['scheduled_at']),
                'place' => $data['place'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['date' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json($plan->fresh(), 201)
            : redirect('/dates')->with('status', 'Ajakan kencan terkirim 💘');
    }

    public function respond(Request $request, DatePlan $datePlan, DatePlanService $dates)
    {
        $data = $request->validate(['action' => ['required', 'string', 'in:accept,decline']]);
        try {
            $plan = $dates->respond($request->user(), $datePlan, $data['action']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['date' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json($plan)
            : back()->with('status', $data['action'] === 'accept' ? 'Kencan disepakati 🎉' : 'Ajakan ditolak dengan sopan.');
    }

    public function destroy(Request $request, DatePlan $datePlan, DatePlanService $dates)
    {
        try {
            $dates->cancel($request->user(), $datePlan);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['date' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['message' => 'Cancelled.'])
            : back()->with('status', 'Rencana kencan dibatalkan.');
    }
}
