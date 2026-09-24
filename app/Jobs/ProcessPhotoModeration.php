<?php

namespace App\Jobs;

use App\Models\ModerationQueue;
use App\Models\ProfilePhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPhotoModeration implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(public int $photoId) {}

    public function handle(): void
    {
        $photo = ProfilePhoto::find($this->photoId);
        if (! $photo) {
            return;
        }
        // Local heuristics only (no external AI in queue default):
        // flag suspicious paths for human review.
        $suspicious = str_contains(strtolower($photo->path), 'screenshot')
            || str_contains(strtolower($photo->path), 'meme');
        if ($suspicious) {
            ModerationQueue::create([
                'queueable_type' => ProfilePhoto::class,
                'queueable_id' => $photo->id,
                'reason' => 'auto photo heuristic',
                'priority' => 4,
                'status' => 'pending',
            ]);
            $photo->update(['is_approved' => false]);
        } else {
            $photo->update(['is_approved' => true]);
        }
    }
}
