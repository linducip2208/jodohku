<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationAction;
use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\ModerationQueue;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModerationController extends Controller
{
    public function queues(Request $request)
    {
        $items = ModerationQueue::latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.moderation.queues', ['items' => $items]);
    }

    public function decide(Request $request, ModerationQueue $queue, AuditService $audit)
    {
        $request->validate([
            'action' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $queue, $audit) {
            $queue->update(['status' => 'resolved', 'handled_by' => $request->user()->id, 'resolved_at' => now()]);
            ModerationLog::create([
                'moderation_queue_id' => $queue->id,
                'moderator_id' => $request->user()->id,
                'action' => $request->input('action'),
                'notes' => $request->input('notes'),
            ]);
            $audit->log('admin.moderation.decided', $request->user(), $queue, [], ['action' => $request->input('action')]);
        });

        return response()->json(['message' => 'Decision recorded.']);
    }
}
