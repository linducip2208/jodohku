<?php

namespace App\Jobs;

use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Async media optimization (stories): resize + re-encode off-request.
 * Uploads store the original synchronously so the request stays fast and
 * never fails on image processing; this job replaces it with the
 * optimized variant. CDN-ready via the public disk abstraction.
 */
class ProcessMediaUpload implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 300;

    public function __construct(public int $storyId) {}

    public function handle(): void
    {
        $story = Story::find($this->storyId);
        if (! $story || ! $story->media_path) {
            return;
        }
        if (! Storage::disk('public')->exists($story->media_path)) {
            return;
        }
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read(Storage::disk('public')->path($story->media_path));
            if (method_exists($image, 'scaleDown')) {
                $image->scaleDown(1600, 1600);
            }
            Storage::disk('public')->put($story->media_path, (string) $image->encode(new JpegEncoder(82)));
        } catch (\Throwable) {
            // Original stays usable; optimization is best-effort.
        }
    }
}
