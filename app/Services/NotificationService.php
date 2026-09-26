<?php

namespace App\Services;

use App\Events\SocialNotificationBroadcast;
use App\Models\User;
use App\Notifications\BroadcastMessage;
use App\Notifications\ChatRequestReceived;
use App\Notifications\ConsultationStatusChanged;
use App\Notifications\CourtshipStageChanged;
use App\Notifications\DateProposed;
use App\Notifications\DateReminder;
use App\Notifications\MatchFound;
use App\Notifications\MatchNudge;
use App\Notifications\NewMessage;
use App\Notifications\PaymentNotification;
use App\Notifications\ReportStatusChanged;
use App\Notifications\SubscriptionActive;
use App\Notifications\VerificationDecided;
use App\Services\Push\PushService;
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
        if ($prefs && ($notification instanceof DateProposed || $notification instanceof DateReminder) && ! ($prefs->push_dates ?? true)) {
            return;
        }
        // Like / super-like pushes share the like preference gates.
        if ($prefs && str_contains(get_class($notification), 'Like') && ! $prefs->push_likes) {
            return;
        }
        if ($prefs && str_contains(get_class($notification), 'SuperLike') && ! $prefs->push_super_likes) {
            return;
        }
        // Social graph gates.
        if ($prefs && $notification instanceof SocialFollow && ! $prefs->push_follows) {
            return;
        }
        if ($prefs && $notification instanceof PostCommented && ! $prefs->push_comments) {
            return;
        }
        if ($prefs && $notification instanceof Mentioned && ! $prefs->push_mentions) {
            return;
        }
        if ($prefs && $notification instanceof PostReacted && ! ($prefs->push_likes || $prefs->push_comments)) {
            return;
        }
        if ($this->isDuplicateUnread($user, $notification)) {
            return;
        }
        $user->notify($notification);
        // Realtime nudge for social types (database row stays the source of
        // truth). Best-effort: broadcast failures never fail the send.
        if ($notification instanceof SocialFollow || $notification instanceof PostReacted
            || $notification instanceof PostCommented || $notification instanceof Mentioned
            || $notification instanceof StoryReacted) {
            try {
                $data = $notification->toDatabase($user);
                SocialNotificationBroadcast::dispatch(
                    (int) $user->id, class_basename($notification), (string) ($data['title'] ?? 'Notifikasi baru'));
            } catch (\Throwable) {
            }
        }
        // Push leg (same gating as above already applied). Best-effort.
        try {
            $data = method_exists($notification, 'toDatabase') ? $notification->toDatabase($user) : [];
            $data = is_array($data) ? $data : [];
            $title = (string) ($data['title'] ?? class_basename($notification));
            $body = (string) ($data['body'] ?? $data['preview'] ?? '');
            $url = app(PushService::class)->deepLink(class_basename($notification), $data);
            app(PushService::class)->fanout($user, $title, $body, array_filter(['url' => $url]));
        } catch (\Throwable) {
        }
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
            SocialFollow::class => 'follower_id',
            PostReacted::class => 'post_id',
            PostCommented::class => 'comment_id',
            Mentioned::class => 'mentionable_id',
            StoryReacted::class => 'story_id',
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
