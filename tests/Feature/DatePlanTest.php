<?php

namespace Tests\Feature;

use App\Jobs\SendDateReminders;
use App\Models\DatePlan;
use App\Models\User;
use App\Services\DatePlanService;
use App\Services\LikeService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function matched(int $n = 2): array
    {
        $users = User::factory()->count($n)->create();
        app(LikeService::class)->like($users[0], $users[1]);
        app(LikeService::class)->like($users[1], $users[0]);

        return [$users[0]->fresh(), $users[1]->fresh()];
    }

    public function test_propose_accept_remind_flow(): void
    {
        [$a, $b] = $this->matched();
        $svc = app(DatePlanService::class);

        $plan = $svc->propose($a, $b, ['scheduled_at' => now()->addHours(5), 'place' => 'Kopi Senja']);
        $this->assertSame('proposed', $plan->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $b->id]);

        // Only partner responds.
        try {
            $svc->respond($a, $plan, 'accept');
            $this->fail('Proposer harus ditolak.');
        } catch (\RuntimeException) {
        }
        $plan = $svc->respond($b, $plan, 'accept');
        $this->assertSame('accepted', $plan->status);

        // Reminder job fires once (idempotent via reminded_at).
        $job = new SendDateReminders;
        $job->handle($svc, app(NotificationService::class));
        $this->assertNotNull($plan->fresh()->reminded_at);
        $job->handle($svc, app(NotificationService::class));
        $this->assertSame(1, DatePlan::whereNotNull('reminded_at')->count());
    }

    public function test_stranger_and_past_rejected(): void
    {
        [$a, $b] = $this->matched();
        $stranger = User::factory()->create();
        $svc = app(DatePlanService::class);

        try {
            $svc->propose($a, $stranger, ['scheduled_at' => now()->addHours(5)]);
            $this->fail('Stranger harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('match', $e->getMessage());
        }
        try {
            $svc->propose($a, $b, ['scheduled_at' => now()->addMinutes(10)]);
            $this->fail('Jadwal mepet harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('1 jam', $e->getMessage());
        }
        $this->assertSame(0, DatePlan::count());
    }

    public function test_web_and_api_wiring(): void
    {
        [$a, $b] = $this->matched();
        $res = $this->actingAs($a)->post('/dates', [
            'partner_id' => $b->id, 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'),
        ])->assertRedirect('/dates');
        $plan = DatePlan::latest('id')->firstOrFail();
        $this->actingAs($b)->post("/dates/{$plan->id}/respond", ['action' => 'accept'])->assertRedirect();
        $this->assertSame('accepted', $plan->fresh()->status);
        $this->actingAs($a)->get('/dates')->assertOk();

        $json = $this->actingAs($a, 'sanctum')->getJson('/api/v1/dates')->assertOk()->json();
        $this->assertNotEmpty($json['data'] ?? $json);
    }
}
