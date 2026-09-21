<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudEvent;
use App\Models\FraudRiskScore;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FraudDetectionService;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    public function index(Request $request)
    {
        $events = FraudEvent::with('user')->latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($events) : view('admin.fraud', ['events' => $events]);
    }

    public function score(Request $request, User $user, FraudDetectionService $fraud)
    {
        return response()->json($fraud->scoreUser($user));
    }

    public function resolve(Request $request, FraudEvent $event, AuditService $audit)
    {
        $request->validate(['resolution' => ['required', 'string', 'max:1000']]);
        $event->update(['status' => 'resolved', 'resolution' => $request->input('resolution'), 'handled_by' => $request->user()->id]);
        $audit->log('admin.fraud.resolved', $request->user(), $event);

        return response()->json(['message' => 'Resolved.']);
    }
}
