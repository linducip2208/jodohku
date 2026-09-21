<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\BoostService;
use App\Services\CreditService;
use Illuminate\Http\Request;

class BoostController extends Controller
{
    public function status(Request $request, BoostService $boost)
    {
        return response()->json(['live' => $boost->isLive($request->user())]);
    }

    public function activate(Request $request, BoostService $boost, CreditService $credits)
    {
        $request->validate(['duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480']]);
        if ($boost->isLive($request->user())) {
            return response()->json(['message' => 'Boost already active.'], 422);
        }
        try {
            $credits->spend($request->user(), 50, 'Profile boost');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $record = $boost->activate($request->user(), (int) $request->input('duration_minutes', 30));

        return response()->json($record, 201);
    }
}
