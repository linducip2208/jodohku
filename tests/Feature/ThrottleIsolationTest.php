<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel 13 keys authenticated throttle buckets by user. Every inline
 * limiter MUST carry a distinct prefix, otherwise the strictest limit
 * (e.g. 10/min) silently caps the whole API for active users.
 */
class ThrottleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_strict_buckets_do_not_bleed_across_routes(): void
    {
        $u = User::factory()->create();
        $v = User::factory()->create();

        // 13 authed calls on assorted routes...
        for ($i = 0; $i < 13; $i++) {
            $this->actingAs($u, 'sanctum')->getJson('/api/v1/wallet')->assertOk();
        }
        // ...must NOT exhaust the reports bucket (10/min of its own).
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/reports', [
            'reported_user_id' => $v->id, 'reason' => 'spam', 'details' => 'x',
        ])->assertCreated();

        // And the reports bucket itself still enforces its own cap.
        for ($i = 0; $i < 9; $i++) {
            $this->actingAs($u, 'sanctum')->postJson('/api/v1/reports', [
                'reported_user_id' => $v->id, 'reason' => 'spam', 'details' => 'x',
            ]);
        }
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/reports', [
            'reported_user_id' => $v->id, 'reason' => 'spam', 'details' => 'x',
        ])->assertStatus(429);
    }
}
