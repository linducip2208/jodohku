<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'report_status',
            'report_id' => $this->report->id,
            'status' => $this->report->status->value ?? (string) $this->report->status,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Status laporanmu diperbarui')
            ->line('Laporan #'.$this->report->id.' kini berstatus: '.($this->report->status->value ?? ''))
            ->line('Terima kasih telah membantu menjaga '.config('app.name').' aman.');
    }
}
