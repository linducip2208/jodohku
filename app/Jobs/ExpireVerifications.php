<?php

namespace App\Jobs;

use App\Services\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireVerifications implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 300;

    public function handle(VerificationService $verifications): void
    {
        $verifications->expireDue(500);
    }
}
