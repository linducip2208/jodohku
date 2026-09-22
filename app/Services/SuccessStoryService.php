<?php

namespace App\Services;

use App\Enums\SuccessStoryStatus;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SuccessStoryService
{
    public function __construct(protected AuditService $audit) {}

    public function submit(User $user, array $data): SuccessStory
    {
        return DB::transaction(function () use ($user, $data) {
            $story = SuccessStory::create([
                'user_id' => $user->id,
                'partner_name' => $data['partner_name'],
                'story' => $data['story'],
                'photo_path' => $data['photo_path'] ?? null,
                'status' => SuccessStoryStatus::Pending,
            ]);
            $this->audit->log('success_story.submitted', $user, $story);

            return $story->fresh();
        });
    }

    public function publish(SuccessStory $story, User $reviewer): SuccessStory
    {
        $story->update(['status' => SuccessStoryStatus::Published, 'published_at' => now()]);
        $this->audit->log('success_story.published', $reviewer, $story);

        return $story->fresh();
    }

    public function reject(SuccessStory $story, User $reviewer): SuccessStory
    {
        $story->update(['status' => SuccessStoryStatus::Rejected, 'published_at' => null]);
        $this->audit->log('success_story.rejected', $reviewer, $story);

        return $story->fresh();
    }

    public function published(int $perPage = 20)
    {
        return SuccessStory::published()->with('user')->latest('published_at')->paginate($perPage);
    }

    public function mine(User $user, int $perPage = 20)
    {
        return SuccessStory::where('user_id', $user->id)->latest('id')->paginate($perPage);
    }

    public function queue(int $perPage = 25)
    {
        return SuccessStory::where('status', SuccessStoryStatus::Pending)->with('user')->latest('id')->paginate($perPage);
    }
}
