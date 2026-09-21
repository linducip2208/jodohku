<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\OperatorAssignment;
use App\Models\VirtualConversation;
use App\Services\OperatorService;
use Illuminate\Http\Request;

class OperatorController extends Controller
{
    public function queue(Request $request)
    {
        return response()->json(VirtualConversation::open()->with(['realUser', 'conversation'])->paginate(25));
    }

    public function takeover(Request $request, VirtualConversation $vc, OperatorService $operators)
    {
        $assignment = $operators->takeover($vc, $request->user());

        return response()->json($assignment, 201);
    }

    public function pause(Request $request, VirtualConversation $vc, OperatorService $operators)
    {
        $operators->pause($vc, $request->user());

        return response()->json(['message' => 'Paused.']);
    }

    public function resume(Request $request, VirtualConversation $vc, OperatorService $operators)
    {
        $operators->resume($vc, $request->user());

        return response()->json(['message' => 'Resumed.']);
    }

    public function assign(Request $request, Conversation $conversation, OperatorService $operators)
    {
        $request->validate(['virtual_profile_id' => ['nullable', 'integer', 'exists:virtual_profiles,id']]);
        $assignment = $operators->assign($conversation, $request->user(), $request->input('virtual_profile_id'));

        return response()->json($assignment, 201);
    }

    public function close(Request $request, VirtualConversation $vc, OperatorService $operators)
    {
        $operators->close($vc, $request->user());

        return response()->json(['message' => 'Closed.']);
    }

    public function assignments(Request $request)
    {
        return response()->json(OperatorAssignment::with(['operator', 'conversation'])->latest('id')->paginate(25));
    }
}
