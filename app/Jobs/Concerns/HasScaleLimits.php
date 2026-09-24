<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Scale defaults for every queued job (SCALE-AUDIT P0).
 *
 * NOTE: this trait intentionally does NOT define $tries/$timeout properties:
 * PHP fatals when a class overrides a trait property with a different
 * default. Each job declares its own $tries/$timeout explicitly (defaults
 * 3/120 unless the job needs custom values); the trait only provides the
 * shared backoff schedule and failed-job logging.
 */
trait HasScaleLimits
{
    /** Backoff seconds between retries (index = retry number). */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /** Never let a failed job vanish silently — ops watches failed_jobs. */
    public function failed(\Throwable $exception): void
    {
        try {
            Log::warning('Job failed: '.static::class, [
                'error' => substr($exception->getMessage(), 0, 500),
            ]);
        } catch (\Throwable) {
        }
    }
}
