<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Counselor;
use App\Models\Coupon;
use App\Models\Interest;
use App\Models\MatchScore;
use App\Models\User;
use App\Services\ConsultationService;
use App\Services\CouponService;
use App\Services\DiscoveryService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryPaginationScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_pages_are_full_without_duplicates(): void
    {
        $me = User::factory()->create();
        User::factory()->count(12)->create();
        $svc = app(DiscoveryService::class);

        $p1 = $svc->discover($me, [], 5);
        $this->assertCount(5, $p1->items());
        $this->assertTrue($p1->hasMorePages());
        $this->assertNotNull($p1->nextCursor());

        $p2 = $svc->discover($me, [], 5, $p1->nextCursor()->encode());
        $ids1 = collect($p1->items())->pluck('id')->all();
        $ids2 = collect($p2->items())->pluck('id')->all();
        $this->assertEmpty(array_intersect($ids1, $ids2));
        $this->assertCount(5, $p2->items());

        // Exhaust the pool: final page has no next cursor.
        $cursor = $p2->nextCursor()?->encode();
        $last = $p2;
        for ($i = 0; $i < 5 && $cursor; $i++) {
            $last = $svc->discover($me, [], 5, $cursor);
            $cursor = $last->nextCursor()?->encode();
        }
        $this->assertNull($cursor);
        $this->assertFalse($last->hasMorePages());
    }

    public function test_score_many_reuses_fresh_match_scores(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $engine = app(MatchingEngine::class);

        $first = $engine->scoreMany($a, collect([$b]));
        $this->assertArrayHasKey($b->id, $first);
        $this->assertEquals(1, MatchScore::count());

        // Second call hits the read-path: no new rows, same mutual score.
        $second = $engine->scoreMany($a, collect([$b]));
        $this->assertEquals(1, MatchScore::count());
        $this->assertEquals($first[$b->id]['mutual'], $second[$b->id]['mutual']);
    }

    public function test_consultation_overlap_rejected_portably(): void
    {
        $counselorUser = User::factory()->create();
        $counselor = Counselor::create(['user_id' => $counselorUser->id, 'specialty' => 'Taaruf', 'is_active' => true]);
        $svc = app(ConsultationService::class);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $at = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
        $svc->book($u1, $counselor, [
            'topic' => 'Taaruf',
            'scheduled_at' => $at->toDateTimeString(),
            'duration_minutes' => 60,
        ]);
        // Overlapping slot (10:30–11:00) must be rejected on any DB driver.
        try {
            $svc->book($u2, $counselor, [
                'topic' => 'Taaruf',
                'scheduled_at' => $at->copy()->addMinutes(30)->toDateTimeString(),
                'duration_minutes' => 30,
            ]);
            $this->fail('Expected overlap rejection.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already booked', $e->getMessage());
        }
        // Adjacent slot (11:00) is fine.
        $ok = $svc->book($u2, $counselor, [
            'topic' => 'Taaruf',
            'scheduled_at' => $at->copy()->addMinutes(60)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);
        $this->assertTrue($ok->exists);
        $this->assertEquals(2, Consultation::count());
    }

    public function test_coupon_redemption_idempotent_per_payment(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'code' => 'HEMAT20', 'name' => 'Hemat 20rb', 'type' => 'fixed', 'value' => 20000,
            'usage_limit' => 100, 'per_user_limit' => 5, 'used_count' => 0,
            'is_active' => true,
        ]);
        $svc = app(CouponService::class);
        $payment = $user->payments()->create([
            'gateway' => 'midtrans', 'amount' => 100000, 'total_amount' => 80000,
            'currency' => 'IDR', 'status' => 'pending',
        ]);

        $svc->recordRedemption($coupon, $user, (int) $payment->id, 20000.0);
        $svc->recordRedemption($coupon, $user, (int) $payment->id, 20000.0);
        $this->assertEquals(1, $coupon->redemptions()->where('payment_id', $payment->id)->count());
        $this->assertEquals(1, $coupon->fresh()->used_count);
    }

    public function test_profile_completeness_counts_photo_and_interests(): void
    {
        $user = User::factory()->create();
        $profile = $user->profile()->create([
            'headline' => 'Serius mencari', 'bio' => str_repeat('Baik. ', 20),
            'occupation' => 'Guru', 'education' => 'S1', 'religion' => 'Islam',
            'height_cm' => 165, 'relationship_goal' => 'marriage',
        ]);
        $bare = $profile->completenessScore();
        $this->assertGreaterThanOrEqual(70, $bare);
        $this->assertLessThan(100, $bare);

        $user->photos()->create(['path' => 'x.jpg', 'status' => 'approved']);
        $ids = Interest::factory()->count(3)->create()->pluck('id')->all();
        $user->interests()->sync($ids);
        $this->assertEquals(100, $profile->fresh()->completenessScore());
        $this->assertEquals(100, $profile->fresh()->completenessScore());
    }
}
