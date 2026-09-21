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
        if ($this->isDuplicateUnread($user, $notification)) {
            return;
        }
        $user->notify($notification);
    }

    /** Same entity + same type already waiting unread → don't stack. */
    protected function isDuplicateUnread(User $user, \Illuminate\Notifications\Notification $notification): bool
    {
        $keyMap = [
            \App\Notifications\MatchFound::class => 'match_id',
            \App\Notifications\NewMessage::class => 'message_id',
            \App\Notifications\ChatRequestReceived::class => 'request_id',
            \App\Notifications\PaymentNotification::class => 'payment_id',
            \App\Notifications\SubscriptionActive::class => 'subscription_id',
            \App\Notifications\VerificationDecided::class => 'request_id',
            \App\Notifications\ReportStatusChanged::class => 'report_id',
        ];
        $class = get_class($notification);
        if (! isset($keyMap[$class]) || ! method_exists($notification, 'toDatabase')) {
            return false;
        }
        try {
            $data = $notification->toDatabase($user);
        } catch (\Throwable) {
            return false;
        }
        $key = $keyMap[$class];
        if (empty($data[$key])) {
            return false;
        }

        return $user->unreadNotifications()
            ->where('type', $class)
            ->where("data->{$key}", $data[$key])
            ->exists();
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
