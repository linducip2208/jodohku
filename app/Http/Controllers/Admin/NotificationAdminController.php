<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\BroadcastMessage;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationAdminController extends Controller
{
    public function inbox(Request $request)
    {
        $messages = ContactMessage::latest('id')->paginate(25);

        return $request->wantsJson()
            ? response()->json($messages)
            : view('admin.inbox', ['messages' => $messages]);
    }

    public function handle(Request $request, int $message, AuditService $audit)
    {
        $m = ContactMessage::findOrFail($message);
        $request->validate(['status' => ['required', 'string', 'in:handled,spam,open']]);
        $m->update(['status' => $request->string('status'), 'handled_by' => $request->user()->id]);
        $audit->log('admin.contact.handled', $request->user(), $m, [], ['status' => $m->status]);

        return $request->wantsJson() ? response()->json($m->fresh()) : back()->with('status', 'Tiket diperbarui.');
    }

    public function broadcast(Request $request, NotificationService $notifications, AuditService $audit)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'in:all,premium,free,verified'],
        ]);
        $broadcastId = (string) Str::ulid();
        $title = (string) $request->input('title');
        $body = (string) $request->input('body');
        $query = User::active();
        match ($request->input('audience', 'all')) {
            'premium' => $query->premium(),
            'verified' => $query->verified(),
            'free' => $query->where('is_premium', false),
            default => null,
        };
        $count = 0;
        // Queued via ShouldQueue: chunk keeps memory low, mail honors opt-out in via().
        $query->chunkById(500, function ($users) use ($notifications, &$count, $title, $body, $broadcastId) {
            foreach ($users as $user) {
                $notifications->send($user, new BroadcastMessage($title, $body, $broadcastId));
                $count++;
            }
        });
        $audit->log('admin.notification.broadcast', $request->user(), null, [], ['audience' => $request->input('audience', 'all'), 'count' => $count, 'broadcast_id' => $broadcastId]);

        return response()->json(['sent' => $count, 'broadcast_id' => $broadcastId]);
    }
}
