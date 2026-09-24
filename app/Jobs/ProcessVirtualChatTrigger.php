<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\VirtualMemberService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVirtualChatTrigger implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(public string $eventName, public int $userId, public array $context = []) {}

    public function handle(VirtualMemberService $virtual): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }
        $virtual->handleEvent($this->eventName, $user, $this->context);
    }
}
