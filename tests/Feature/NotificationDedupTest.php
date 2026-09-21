<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MatchFound;
use App\Services\LikeService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_match_notification_not_stacked(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        /** @var LikeService $likes */
        $likes = app(LikeService::class);
        $likes->like($a, $b);
        $result = $likes->like($b, $a);
        $match = $result['match'];
        $this->assertNotNull($match);

        $before = $b->notifications()->count();
        $this->assertGreaterThanOrEqual(1, $before);

        /** @var NotificationService $svc */
        $svc = app(NotificationService::class);
        $svc->send($b, new MatchFound($match->fresh(), $a));
        $svc->send($b, new MatchFound($match->fresh(), $a));

        $this->assertEquals($before, $b->notifications()->count());
    }
}
