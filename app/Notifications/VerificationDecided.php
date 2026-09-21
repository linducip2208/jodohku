<?php

namespace App\Notifications;

use App\Models\VerificationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VerificationRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'verification_decided',
            'request_id' => $this->request->id,
            'status' => $this->request->status->value ?? (string) $this->request->status,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = ($this->request->status->value ?? '') === 'approved';

        return (new MailMessage)
            ->subject($approved ? 'Verifikasi disetujui!' : 'Update verifikasi akunmu')
            ->line($approved ? 'Selamat! Akunmu kini terverifikasi.' : 'Mohon maaf, verifikasi belum disetujui. '.$this->request->notes)
            ->action('Lihat Profil', url('/profile'));
    }
}
