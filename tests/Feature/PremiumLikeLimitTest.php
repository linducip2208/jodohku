<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\LikeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumLikeLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_member_capped_premium_unlimited(): void
    {
        config()->set('jodohku.limits.free_daily_likes', 2);
        $free = User::factory()->create();
        $premium = User::factory()->create(['is_premium' => true]);

        /** @var LikeService $svc */
        $svc = app(LikeService::class);

        $svc->like($free, User::factory()->create());
        $svc->like($free, User::factory()->create());
        $this->assertEquals(0, $svc->likesRemainingToday($free));
        try {
            $svc->like($free, User::factory()->create());
            $this->fail('Limit should throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Premium', $e->getMessage());
        }

        for ($i = 0; $i < 5; $i++) {
            $svc->like($premium, User::factory()->create());
        }
        $this->assertEquals('unlimited', $svc->likesRemainingToday($premium));

        // HTTP layer maps the limit to 403 + upgrade flag.
        $target = User::factory()->create();
        $this->actingAs($free, 'sanctum')
            ->postJson('/api/v1/likes/'.$target->id)
            ->assertForbidden()
            ->assertJson(['upgrade' => true]);
    }

    public function test_who_liked_and_visitors_require_premium(): void
    {
        $free = User::factory()->create();
        $premium = User::factory()->create(['is_premium' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($free, 'sanctum')->getJson('/api/v1/who-liked')->assertForbidden();
        $this->actingAs($free, 'sanctum')->getJson('/api/v1/visitors')->assertForbidden();
        $this->actingAs($premium, 'sanctum')->getJson('/api/v1/who-liked')->assertOk();
        $this->actingAs($premium, 'sanctum')->getJson('/api/v1/visitors')->assertOk();
        $this->assertTrue($admin->isAdmin());
    }
}
