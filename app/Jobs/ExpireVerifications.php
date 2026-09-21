<?php

namespace App\Jobs;

use App\Services\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireVerifications implements ShouldQueue
{
    use Queueable;

    public function handle(VerificationService $verifications): void
    {
        $verifications->expireDue(500);
    }
}
