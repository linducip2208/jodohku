<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMatch;
use App\Services\LikeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutualLikeCreatesMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutual_like_creates_canonical_idempotent_match(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        /** @var LikeService $likes */
        $likes = app(LikeService::class);

        $first = $likes->like($a, $b);
        $this->assertNull($first['match']);
        $this->assertFalse($first['is_new_match']);
        $this->assertEquals(0, UserMatch::count());

        $second = $likes->like($b, $a);
        $this->assertNotNull($second['match']);
        $this->assertTrue($second['is_new_match']);
        $this->assertEquals(1, UserMatch::count());

        $match = UserMatch::forPair($a->id, $b->id);
        $this->assertNotNull($match);
        $this->assertTrue($match->is_active);
        // Canonical ordering: user_a_id is the smaller id.
        $this->assertEquals(min($a->id, $b->id), $match->user_a_id);
        $this->assertEquals(max($a->id, $b->id), $match->user_b_id);

        // Re-liking either direction must not duplicate the match.
        $likes->like($a, $b);
        $likes->like($b, $a);
        $this->assertEquals(1, UserMatch::count());
    }
}
