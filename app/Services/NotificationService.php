<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\BroadcastMessage;
use App\Notifications\ChatRequestReceived;
use App\Notifications\ConsultationStatusChanged;
use App\Notifications\CourtshipStageChanged;
use App\Notifications\MatchFound;
use App\Notifications\MatchNudge;
use App\Notifications\NewMessage;
use App\Notifications\PaymentNotification;
use App\Notifications\ReportStatusChanged;
use App\Notifications\SubscriptionActive;
use App\Notifications\VerificationDecided;
use Illuminate\Notifications\Notification;

class NotificationService
{
    /** In-app (database) + mail via Notification classes. */
    public function send(User $user, Notification $notification): void
    {
        $prefs = $user->notificationPreference;
        // Respect coarse preferences for match/message/like categories.
        if ($prefs && $notification instanceof MatchFound && ! $prefs->push_matches) {
            return;
        }
        if ($prefs && $notification instanceof NewMessage && ! $prefs->push_messages) {
            return;
        }
        if ($prefs && $notification instanceof MatchNudge && ! $prefs->push_matches) {
            return;
        }
        if ($prefs && $notification instanceof ChatRequestReceived && ! $prefs->push_messages) {
            return;
        }
        // Like / super-like pushes share the like preference gates.
        if ($prefs && str_contains(get_class($notification), 'Like') && ! $prefs->push_likes) {
            return;
        }
        if ($prefs && str_contains(get_class($notification), 'SuperLike') && ! $prefs->push_super_likes) {
            return;
        }
        if ($this->isDuplicateUnread($user, $notification)) {
            return;
        }
        $user->notify($notification);
    }

    /** Same entity + same type already waiting unread → don't stack. */
    protected function isDuplicateUnread(User $user, Notification $notification): bool
    {
        $keyMap = [
            MatchFound::class => 'match_id',
            NewMessage::class => 'message_id',
            ChatRequestReceived::class => 'request_id',
            PaymentNotification::class => 'payment_id',
            SubscriptionActive::class => 'subscription_id',
            VerificationDecided::class => 'request_id',
            ReportStatusChanged::class => 'report_id',
            CourtshipStageChanged::class => 'courtship_id',
            ConsultationStatusChanged::class => 'consultation_id',
            BroadcastMessage::class => 'broadcast_id',
            MatchNudge::class => 'match_id',
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
