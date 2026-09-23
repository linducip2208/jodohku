<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\MatchScore;
use App\Models\SuperLike;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\LikeService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairDiscoveryMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function matchedPair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);

        return [$a, $b];
    }

    public function test_stats_matches_today_grouping(): void
    {
        [$a, $b] = $this->matchedPair();
        // Old match where $a is user_a must NOT count as today (orWhere grouping bug).
        $c = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($a->id, $c->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()->subDays(2)]);
        // Someone else's match today must not leak into $a stats.
        $d = User::factory()->create();
        $e = User::factory()->create();
        [$u3, $u4] = UserMatch::canonical($d->id, $e->id);
        UserMatch::create(['user_a_id' => $u3, 'user_b_id' => $u4, 'is_active' => true, 'matched_at' => now()]);

        $this->actingAs($a)->getJson('/api/v1/matches/stats')->assertOk()
            ->assertJsonPath('matches_today', 1)
            ->assertJsonPath('matches_total', 2);

        // $b sees the same match from the user_b side.
        $this->actingAs($b)->getJson('/api/v1/matches/stats')->assertOk()
            ->assertJsonPath('matches_today', 1)
            ->assertJsonPath('matches_total', 1);
    }

    public function test_superlike_free_daily_quota_enforced(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        $this->actingAs($a)->postJson("/api/v1/superlikes/{$b->id}")->assertCreated();
        // Second superlike same day exceeds the free daily quota (default 1).
        $this->actingAs($a)->postJson("/api/v1/superlikes/{$c->id}")
            ->assertForbidden()->assertJsonPath('upgrade', true);
        $this->assertEquals(1, SuperLike::where('sender_id', $a->id)->count());
    }

    public function test_rewind_cooldown_enforced(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson("/api/v1/passes/{$b->id}")->assertOk(); // pass
        $this->actingAs($a)->postJson('/api/v1/rewind')->assertOk(); // first undo ok
        // Immediate second undo hits cooldown.
        $this->actingAs($a)->postJson('/api/v1/rewind')->assertStatus(429);
    }

    public function test_rewind_without_cooldown_config(): void
    {
        config()->set('jodohku.limits.rewind_cooldown_minutes', 0);
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson("/api/v1/passes/{$b->id}")->assertOk();
        $this->actingAs($a)->postJson('/api/v1/rewind')->assertOk()->assertJsonPath('undone', 'pass');
    }

    public function test_unlike_endpoint_removes_like_without_pass_rewind(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson("/api/v1/likes/{$b->id}")->assertCreated();
        $this->actingAs($a)->deleteJson("/api/v1/likes/{$b->id}")->assertOk();
        $this->assertFalse(Like::where('liker_id', $a->id)->where('liked_id', $b->id)->exists());
        // Unlike is idempotent.
        $this->actingAs($a)->deleteJson("/api/v1/likes/{$b->id}")->assertOk();
    }

    public function test_unlike_and_pass_deactivate_match(): void
    {
        $svc = app(LikeService::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $svc->like($a, $b);
        $svc->like($b, $a);
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        $this->assertTrue(UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->first()->is_active);

        // Pass removes the like AND deactivates the now non-mutual match.
        $svc->pass($a, $b);
        $this->assertFalse(Like::where('liker_id', $a->id)->where('liked_id', $b->id)->exists());
        $this->assertFalse(UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->first()->is_active);

        // Re-like both ways, then unlike has the same effect.
        $svc->like($a, $b);
        $svc->like($b, $a);
        $svc->unlike($a, $b);
        $match = UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->first();
        $this->assertFalse($match->is_active);
        // Unlike is idempotent.
        $this->assertTrue($svc->unlike($a, $b));
    }

    public function test_persist_score_writes_single_canonical_row(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $engine = app(MatchingEngine::class);

        $engine->persistScore($b, $a); // non-canonical order first
        $engine->persistScore($a, $b);
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        $this->assertEquals(1, MatchScore::where('user_id', $u1)->where('candidate_id', $u2)->count());
        $this->assertEquals(1, MatchScore::count());
    }

    public function test_demographic_breakdown_counts_are_consistent(): void
    {
        $me = User::factory()->create();
        User::factory()->count(4)->create(['is_verified' => true]);
        User::factory()->count(2)->create();
        $engine = app(MatchingEngine::class);

        $out = $engine->demographicBreakdown($me);
        $expectedTotal = User::active()->where('id', '!=', $me->id)->count();
        $this->assertEquals($expectedTotal, $out['total']);
        $this->assertEquals($expectedTotal, (int) collect($out['gender'])->sum());
        $this->assertEquals(4, $out['verified_count']);
        $this->assertEquals(round(4 / $expectedTotal * 100, 1), $out['verified_pct']);
    }
}
