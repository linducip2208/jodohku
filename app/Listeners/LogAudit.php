<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Events\MutualMatchCreated;
use App\Events\PaymentPaid;
use App\Events\ProfileLiked;
use App\Events\ReportCreated;
use App\Events\UserRegistered;
use App\Services\AuditService;
use Illuminate\Events\Dispatcher;

class LogAudit
{
    public function __construct(protected AuditService $audit) {}

    public function handleUserRegistered(UserRegistered $e): void
    {
        $this->audit->log('user.registered', $e->user, $e->user);
    }

    public function handleProfileLiked(ProfileLiked $e): void
    {
        $this->audit->log('profile.liked', $e->liker, $e->liked);
    }

    public function handleMatch(MutualMatchCreated $e): void
    {
        $this->audit->log('match.created', null, $e->match);
    }

    public function handleMessage(MessageSent $e): void
    {
        $this->audit->log('message.sent', $e->message->sender, $e->message);
    }

    public function handlePayment(PaymentPaid $e): void
    {
        $this->audit->log('payment.paid', $e->payment->user, $e->payment);
    }

    public function handleReport(ReportCreated $e): void
    {
        $this->audit->log('report.created', $e->report->reporter, $e->report);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserRegistered::class, [self::class, 'handleUserRegistered']);
        $events->listen(ProfileLiked::class, [self::class, 'handleProfileLiked']);
        $events->listen(MutualMatchCreated::class, [self::class, 'handleMatch']);
        $events->listen(MessageSent::class, [self::class, 'handleMessage']);
        $events->listen(PaymentPaid::class, [self::class, 'handlePayment']);
        $events->listen(ReportCreated::class, [self::class, 'handleReport']);
    }
}
