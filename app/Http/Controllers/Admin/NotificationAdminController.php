<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationAdminController extends Controller
{
    public function inbox(Request $request)
    {
        $messages = \App\Models\ContactMessage::latest('id')->paginate(25);

        return $request->wantsJson()
            ? response()->json($messages)
            : view('admin.inbox', ['messages' => $messages]);
    }

    public function handle(Request $request, int $message, AuditService $audit)
    {
        $m = \App\Models\ContactMessage::findOrFail($message);
        $request->validate(['status' => ['required', 'string', 'in:handled,spam,open']]);
        $m->update(['status' => $request->string('status'), 'handled_by' => $request->user()->id]);
        $audit->log('admin.contact.handled', $request->user(), $m, [], ['status' => $m->status]);

        return $request->wantsJson() ? response()->json($m->fresh()) : back()->with('status', 'Tiket diperbarui.');
    }

    public function broadcast(Request $request, NotificationService $notifications, AuditService $audit)    {
        $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'in:all,premium,free,verified'],
        ]);
        $query = User::active();
        match ($request->input('audience', 'all')) {
            'premium' => $query->premium(),
            'verified' => $query->verified(),
            'free' => $query->where('is_premium', false),
            default => null,
        };
        $count = 0;
        $query->chunkById(500, function ($users) use ($request, $notifications, &$count) {
            foreach ($users as $user) {
                $notifications->send($user, new \App\Notifications\SubscriptionActive($user->activeSubscription()));
                $count++;
            }
        });
        $audit->log('admin.notification.broadcast', $request->user(), null, [], ['audience' => $request->input('audience', 'all'), 'count' => $count]);

        return response()->json(['sent' => $count]);
    }
}
