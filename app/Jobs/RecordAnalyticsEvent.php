<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Async analytics writer — product flows never wait for it. */
class RecordAnalyticsEvent implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 60;

    public function __construct(
        public ?int $userId,
        public string $event,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public array $meta = [],
    ) {}

    public function handle(): void
    {
        AnalyticsEvent::create([
            'user_id' => $this->userId,
            'event' => mb_substr($this->event, 0, 60),
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'meta' => $this->meta ?: null,
        ]);
    }
}
