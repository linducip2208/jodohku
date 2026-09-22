<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MatchFound extends Notification implements ShouldQueue
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
            'type' => 'match_found',
            'match_id' => $this->match->id,
            'other_user_id' => $this->other->id,
            'other_name' => $this->other->displayName(),
            'score' => $this->match->compatibility_score,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kamu punya match baru di Jodohku!')
            ->greeting('Halo '.$notifiable->displayName().'!')
            ->line('Kamu dan '.$this->other->displayName().' saling suka. Skor kecocokan: '.($this->match->compatibility_score ?? '-'))
            ->action('Lihat Match', url('/matches/'.$this->match->id))
            ->line('Jangan sampai kelewatan — sapa duluan ya!');
    }
}
