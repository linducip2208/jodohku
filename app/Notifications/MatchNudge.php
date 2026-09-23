<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public UserMatch $match, public User $other) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $prefs = $notifiable->notificationPreference;
        if (! $prefs || $prefs->email_matches) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'match_nudge',
            'match_id' => $this->match->id,
            'other_user_id' => $this->other->id,
            'other_name' => $this->other->displayName(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sapa match-mu di '.config('app.name').'!')
            ->greeting('Halo '.$notifiable->displayName().'!')
            ->line('Kamu dan '.$this->other->displayName().' sudah match tapi belum ngobrol. Sapa duluan ya!')
            ->action('Buka Chat', url('/chat'));
    }
}
