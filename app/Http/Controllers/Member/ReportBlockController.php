<?php

namespace App\Http\Controllers\Member;

use App\Events\ReportCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\Block;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportBlockController extends Controller
{
    public function report(ReportRequest $request)
    {
        $user = $request->user();
        $report = DB::transaction(function () use ($request, $user) {
            $r = Report::create([
                'reporter_id' => $user->id,
                'reported_user_id' => $request->input('reported_user_id'),
                'reportable_type' => $request->input('reportable_type'),
                'reportable_id' => $request->input('reportable_id'),
                'reason' => $request->input('reason'),
                'details' => $request->input('details'),
                'status' => 'pending',
            ]);
            event(new ReportCreated($r->fresh()));

            return $r;
        });

        return response()->json($report, 201);
    }

    public function block(Request $request, User $user)
    {
        $this->authorize('view', $user);
        Block::firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id]);

        return response()->json(['message' => 'Blocked.'], 201);
    }

    public function unblock(Request $request, User $user)
    {
        Block::where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();

        return response()->json(['message' => 'Unblocked.']);
    }

    public function blocks(Request $request)
    {
        $items = Block::where('blocker_id', $request->user()->id)->with('blocked')->paginate(20);

        return response()->json($items);
    }
}
