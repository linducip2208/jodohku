<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications)
    {
        $user = $request->user();
        $items = $user->notifications()->latest('created_at')->paginate(20);

        return $request->wantsJson()
            ? response()->json(['unread' => $notifications->unreadCount($user), 'notifications' => NotificationResource::collection($items)->response()->getData()])
            : view('member.notifications.center', ['notifications' => $items]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Marked read.']);
    }

    public function readAll(Request $request, NotificationService $notifications)
    {
        $count = $notifications->markAllRead($request->user());

        return response()->json(['marked' => $count]);
    }

    public function preferences(Request $request)
    {
        $pref = $request->user()->notificationPreference()->firstOrCreate([]);

        return response()->json($pref);
    }

    public function updatePreferences(Request $request)
    {
        $request->validate([
            'match_alerts' => ['nullable', 'boolean'],
            'message_alerts' => ['nullable', 'boolean'],
            'like_alerts' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
            'email_enabled' => ['nullable', 'boolean'],
            'push_enabled' => ['nullable', 'boolean'],
        ]);
        $pref = $request->user()->notificationPreference()->firstOrCreate([]);
        $pref->update($request->only(['match_alerts', 'message_alerts', 'like_alerts', 'marketing', 'email_enabled', 'push_enabled']));

        return response()->json($pref->fresh());
    }
}
