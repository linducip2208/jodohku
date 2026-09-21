<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    /** In-app (database) + mail via Notification classes. */
    public function send(User $user, \Illuminate\Notifications\Notification $notification): void
    {
        $prefs = $user->notificationPreference;
        // Respect coarse preferences for match/message categories
        if ($prefs && $notification instanceof \App\Notifications\MatchFound && ! $prefs->push_matches) {
            return;
        }
        if ($prefs && $notification instanceof \App\Notifications\NewMessage && ! $prefs->push_messages) {
            return;
        }
        $user->notify($notification);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
