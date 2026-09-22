<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CompatibilityReport;
use App\Models\Counselor;
use App\Models\SuccessStory;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiroJodohTest extends TestCase
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

    protected function startCourtship(User $a, User $b): int
    {
        return $this->actingAs($a)->postJson('/api/v1/courtships', ['partner_id' => $b->id])
            ->assertCreated()->json('id');
    }

    public function test_courtship_requires_match_and_full_stage_flow(): void
    {
        $a = User::factory()->create();
        $stranger = User::factory()->create();
        $this->actingAs($a)->postJson('/api/v1/courtships', ['partner_id' => $stranger->id])
            ->assertStatus(422);

        [$x, $y] = $this->matchedPair();
        $id = $this->startCourtship($x, $y);

        // Duplicate active courtship rejected.
        $this->actingAs($y)->postJson('/api/v1/courtships', ['partner_id' => $x->id])->assertStatus(422);
        // Outsider cannot view.
        $this->actingAs($stranger)->getJson("/api/v1/courtships/{$id}")->assertForbidden();
        $this->actingAs($x)->getJson("/api/v1/courtships/{$id}")->assertOk()->assertJsonPath('stage', 'kenalan');

        // kenalan -> taaruf.
        $this->actingAs($x)->postJson("/api/v1/courtships/{$id}/advance")->assertOk()->assertJsonPath('stage', 'taaruf');
        // taaruf -> khitbah blocked without guardian approval.
        $this->actingAs($x)->postJson("/api/v1/courtships/{$id}/advance")->assertStatus(422);

        $this->actingAs($y)->putJson("/api/v1/courtships/{$id}/guardian", [
            'guardian_name' => 'H. Ahmad', 'guardian_phone' => '081234567890', 'guardian_relation' => 'Ayah',
        ])->assertOk();
        $this->actingAs($y)->postJson("/api/v1/courtships/{$id}/guardian/approve")->assertOk()
            ->assertJsonPath('guardian_approved_at', fn ($v) => $v !== null);

        $this->actingAs($x)->postJson("/api/v1/courtships/{$id}/advance")->assertOk()->assertJsonPath('stage', 'khitbah');
        $this->actingAs($x)->postJson("/api/v1/courtships/{$id}/advance")->assertOk()
            ->assertJsonPath('stage', 'menikah')
            ->assertJsonPath('status', 'completed');
        // Past final stage rejected.
        $this->actingAs($x)->postJson("/api/v1/courtships/{$id}/advance")->assertStatus(422);
    }

    public function test_courtship_withdraw(): void
    {
        [$x, $y] = $this->matchedPair();
        $id = $this->startCourtship($x, $y);

        $this->actingAs($y)->postJson("/api/v1/courtships/{$id}/withdraw")->assertOk()->assertJsonPath('status', 'withdrawn');
        $this->actingAs($x)->postJson("/api/v1/courtships/{$id}/advance")->assertStatus(422);
        // After withdraw, a fresh courtship may start again.
        $this->actingAs($x)->postJson('/api/v1/courtships', ['partner_id' => $y->id])->assertCreated();
    }

    public function test_consultation_booking_and_transitions(): void
    {
        $member = User::factory()->create();
        $counselorUser = User::factory()->create();
        $counselor = Counselor::create(['user_id' => $counselorUser->id, 'specialty' => 'Pranikah', 'bio' => 'Konselor senior.', 'is_active' => true]);
        $inactive = Counselor::create(['user_id' => User::factory()->create()->id, 'specialty' => 'X', 'is_active' => false]);

        $this->actingAs($member)->getJson('/api/v1/counselors')->assertOk()->assertJsonCount(1, 'data');

        $payload = ['counselor_id' => $counselor->id, 'topic' => 'Kesiapan menikah', 'scheduled_at' => now()->addDay()->toDateTimeString()];
        $id = $this->actingAs($member)->postJson('/api/v1/consultations', $payload)->assertCreated()->json('id');
        // Overlapping booking rejected.
        $this->actingAs($member)->postJson('/api/v1/consultations', $payload)->assertStatus(422);
        // Inactive counselor + past schedule rejected.
        $this->actingAs($member)->postJson('/api/v1/consultations', [
            'counselor_id' => $inactive->id, 'topic' => 'X', 'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(422);
        $this->actingAs($member)->postJson('/api/v1/consultations', [
            'counselor_id' => $counselor->id, 'topic' => 'X', 'scheduled_at' => now()->subHour()->toDateTimeString(),
        ])->assertStatus(422);

        // Member cannot confirm own booking; counselor can.
        $this->actingAs($member)->postJson("/api/v1/consultations/{$id}/confirm")->assertForbidden();
        $this->actingAs($counselorUser)->postJson("/api/v1/consultations/{$id}/confirm")->assertOk()->assertJsonPath('status', 'confirmed');
        // Invalid transition: cancel a completed? complete first.
        $this->actingAs($counselorUser)->postJson("/api/v1/consultations/{$id}/complete")->assertOk()->assertJsonPath('status', 'completed');
        $this->actingAs($member)->postJson("/api/v1/consultations/{$id}/cancel")->assertStatus(422);
    }

    public function test_compatibility_report_generation(): void
    {
        [$a, $b] = $this->matchedPair();

        $res = $this->actingAs($a)->postJson('/api/v1/compatibility-reports', ['candidate_id' => $b->id])
            ->assertCreated()->assertJsonStructure(['score', 'breakdown', 'summary']);
        $this->assertNotEmpty($res->json('summary'));
        // Idempotent: second generate refreshes the same row.
        $this->actingAs($a)->postJson('/api/v1/compatibility-reports', ['candidate_id' => $b->id])->assertCreated();
        $this->assertEquals(1, CompatibilityReport::where('user_id', $a->id)->count());

        $reportId = $res->json('id');
        $this->actingAs($a)->getJson("/api/v1/compatibility-reports/{$reportId}")->assertOk();
        $this->actingAs($b)->getJson("/api/v1/compatibility-reports/{$reportId}")->assertForbidden();
        $this->actingAs($a)->postJson('/api/v1/compatibility-reports', ['candidate_id' => $a->id])->assertStatus(422);
    }

    public function test_success_stories_flow_and_admin_moderation(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($member)->postJson('/api/v1/success-stories', ['partner_name' => 'Sinta', 'story' => 'Singkat'])->assertStatus(422);
        $story = 'Kami bertemu lewat Jodohku pada tahun lalu, menjalani taaruf didampingi wali, lalu menikah bulan Juni. Terima kasih biro jodoh terbaik!';
        $id = $this->actingAs($member)->postJson('/api/v1/success-stories', ['partner_name' => 'Sinta', 'story' => $story])
            ->assertCreated()->assertJsonPath('status', 'pending')->json('id');

        // Pending stories are not public.
        $this->actingAs($member)->getJson('/api/v1/success-stories')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($member)->getJson('/api/v1/success-stories/mine')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($admin)->getJson('/admin/biro-jodoh/stories')->assertOk();
        $this->actingAs($admin)->postJson("/admin/biro-jodoh/stories/{$id}/moderate", ['action' => 'publish'])->assertOk();
        $this->assertEquals('published', SuccessStory::find($id)->status->value);

        $this->actingAs($member)->getJson('/api/v1/success-stories')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/admin/biro-jodoh/stats')->assertOk()->assertJsonPath('stories_published', 1);
    }

    public function test_admin_manages_counselors_and_views_courtships(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create();
        [$x, $y] = $this->matchedPair();
        $this->startCourtship($x, $y);

        $this->actingAs($admin)->getJson('/admin/biro-jodoh/courtships')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($admin)->postJson('/admin/biro-jodoh/counselors', [
            'user_id' => $staff->id, 'specialty' => 'Komunikasi', 'bio' => 'Ahli komunikasi pasangan.',
        ])->assertCreated();
        $cid = Counselor::where('user_id', $staff->id)->first()->id;
        $this->actingAs($admin)->putJson("/admin/biro-jodoh/counselors/{$cid}", ['is_active' => false])->assertOk();
        $this->assertFalse(Counselor::find($cid)->is_active);
    }
}
