<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\Request;

class VerificationAdminController extends Controller
{
    public function index(Request $request)
    {
        $items = VerificationRequest::with(['user', 'documents'])->latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.verification', ['items' => $items]);
    }

    public function approve(Request $request, VerificationRequest $verification, VerificationService $service)
    {
        $service->approve($verification, $request->user());

        return response()->json(['message' => 'Approved.']);
    }

    public function reject(Request $request, VerificationRequest $verification, VerificationService $service)
    {
        $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $service->reject($verification, $request->user(), $request->string('notes'));

        return response()->json(['message' => 'Rejected.']);
    }
}
