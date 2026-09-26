<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\SpeedDatingRound;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\LikeService;
use App\Services\SpeedDatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeedDatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pairings_cover_everyone_without_self(): void
    {
        $svc = app(SpeedDatingService::class);
        // Even count: everyone paired every round.
        $rounds = $svc->pairings([1, 2, 3, 4]);
        $this->assertCount(3, $rounds);
        foreach ($rounds as $pairs) {
            $flat = collect($pairs)->flatten()->all();
            sort($flat);
            $this->assertSame([1, 2, 3, 4], $flat);
        }
        // Odd count: byes rotate, no self-pairs ever.
        $rounds = $svc->pairings([1, 2, 3]);
        $this->assertNotEmpty($rounds);
        foreach ($rounds as $pairs) {
            foreach ($pairs as [$a, $b]) {
                $this->assertNotSame($a, $b);
            }
        }
        // Every unordered pair meets exactly once (4 users → 6 pairs).
        $seen = [];
        foreach ($svc->pairings([1, 2, 3, 4]) as $pairs) {
            foreach ($pairs as [$a, $b]) {
                $seen[] = min($a, $b).'-'.max($a, $b);
            }
        }
        sort($seen);
        $this->assertSame(['1-2', '1-3', '1-4', '2-3', '2-4', '3-4'], $seen);
    }

    public function test_host_generate_idempotent_and_participant_rounds(): void
    {
        $host = User::factory()->create();
        $users = User::factory()->count(4)->create();
        $event = Event::create([
            'host_id' => $host->id, 'title' => 'Speed Night',
            'slug' => 'speed-night-'.uniqid(), 'city' => 'Jakarta',
            'starts_at' => now()->addHour(), 'status' => 'published',
            'format' => 'speed_dating', 'round_minutes' => 5,
        ]);
        foreach ($users as $u) {
            $event->members()->create(['user_id' => $u->id, 'status' => 'confirmed']);
        }
        $svc = app(SpeedDatingService::class);

        // Non-host cannot start.
        try {
            $svc->generate($event, $users[0]);
            $this->fail('Non-host harus ditolak.');
        } catch (\RuntimeException) {
        }

        $rounds = $svc->generate($event, $host);
        $this->assertNotEmpty($rounds);
        $this->assertSame(count($rounds), SpeedDatingRound::where('event_id', $event->id)->count());
        // Idempotent: second call returns existing, no duplicates.
        $again = $svc->generate($event, $host);
        $this->assertSame(count($rounds), count($again));

        // Participant sees own rounds with partner + conversation.
        $mine = $svc->roundsFor($event, $users[0]->fresh());
        $this->assertCount(3, $mine);
        foreach ($mine as $r) {
            $this->assertNotNull($r['partner']);
            $this->assertNotNull($r['conversation_id']);
            $this->assertContains($r['state'], ['live', 'upcoming', 'done']);
        }

        // Web + API wiring.
        $this->actingAs($host)->post("/events/{$event->id}/speed/start")->assertRedirect();
        $this->actingAs($users[0])->get("/events/{$event->id}/speed")->assertOk();
        $this->actingAs($users[0], 'sanctum')->getJson("/api/v1/events/{$event->id}/speed")->assertOk();
    }

    public function test_speed_like_creates_match(): void
    {
        $host = User::factory()->create();
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $event = Event::create([
            'host_id' => $host->id, 'title' => 'Speed Duo',
            'slug' => 'speed-duo-'.uniqid(), 'city' => 'Bandung',
            'starts_at' => now()->addHour(), 'status' => 'published',
            'format' => 'speed_dating', 'round_minutes' => 5,
        ]);
        foreach ([$a, $b] as $u) {
            $event->members()->create(['user_id' => $u->id, 'status' => 'confirmed']);
        }
        app(SpeedDatingService::class)->generate($event, $host);

        app(LikeService::class)->like($a, $b);
        $this->assertSame(0, UserMatch::count());
        app(LikeService::class)->like($b, $a);
        $this->assertSame(1, UserMatch::count());
    }
}
