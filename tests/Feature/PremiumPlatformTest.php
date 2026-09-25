<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Models\Referral;
use App\Models\User;
use App\Services\CandidateRetrievalService;
use App\Services\CreditService;
use App\Services\PassportService;
use App\Services\Push\PushService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PremiumPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_passport_requires_premium_and_drives_discovery(): void
    {
        $free = User::factory()->create();
        $this->expectException(\RuntimeException::class);
        app(PassportService::class)->set($free, ['city' => 'Bandung', 'latitude' => -6.9, 'longitude' => 107.6]);
    }

    public function test_passport_set_clear_and_effective_location(): void
    {
        $premium = User::factory()->create(['is_premium' => true, 'latitude' => -6.2, 'longitude' => 106.8, 'city' => 'Jakarta']);
        $svc = app(PassportService::class);
        $svc->set($premium, ['city' => 'Bandung', 'latitude' => -6.9, 'longitude' => 107.6]);
        $eff = $svc->effectiveLocation($premium->fresh());
        $this->assertTrue($eff['is_passport']);
        $this->assertEquals('Bandung', $eff['city']);

        $this->actingAs($premium)->post('/passport', ['city' => 'Surabaya', 'latitude' => -7.2, 'longitude' => 112.7])->assertRedirect();
        $this->actingAs($premium)->delete('/passport')->assertRedirect();
        $this->assertFalse($svc->active($premium->fresh()));
    }

    public function test_contact_blocking_hashes_and_matches(): void
    {
        $me = User::factory()->create(['phone' => '+628120000001']);
        $other = User::factory()->create(['phone' => '+628120000002']);
        $this->assertNotNull($other->fresh()->phone_hash);

        $res = $this->actingAs($me)->post('/kontak-blokir', ['phones' => "+628120000002\n0812999888777"]);
        $res->assertRedirect();
        $this->assertDatabaseHas('contact_hashes', ['user_id' => $me->id]);
        $this->assertDatabaseMissing('contact_hashes', ['phone_hash' => '+628120000002']);
        $this->assertDatabaseHas('blocks', ['blocker_id' => $me->id, 'blocked_id' => $other->id, 'reason' => 'contact']);
        // Discovery excludes the contact-blocked user.
        $ids = app(CandidateRetrievalService::class)->pool($me->fresh(), [])->pluck('users.id')->all();
        $this->assertNotContains($other->id, $ids);

        $this->actingAs($me)->delete('/kontak-blokir')->assertRedirect();
        $this->assertDatabaseMissing('blocks', ['blocker_id' => $me->id, 'reason' => 'contact']);
    }

    public function test_push_tokens_and_log_fanout(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me, 'sanctum')->postJson('/api/v1/push-tokens', ['platform' => 'android', 'token' => 'tok-123'])->assertCreated();
        $fanout = app(PushService::class)->fanout($me->fresh(), 'Halo', 'Tes', []);
        $this->assertEquals(1, $fanout['sent']);
        $this->actingAs($me, 'sanctum')->deleteJson('/api/v1/push-tokens', ['token' => 'tok-123'])->assertOk();
        $this->assertDatabaseMissing('push_tokens', ['user_id' => $me->id]);
    }

    public function test_referral_attribute_convert_reward(): void
    {
        $referrer = User::factory()->create();
        $referred = User::factory()->create();
        $svc = app(ReferralService::class);
        $code = $svc->codeFor($referrer);
        $this->assertTrue($svc->attributeNewUser($referred, $code));
        $this->assertFalse($svc->attributeNewUser($referred, $code)); // idempotent path kept
        $this->assertFalse($svc->attributeNewUser($referrer, $svc->codeFor($referrer))); // no self-referral

        $before = app(CreditService::class)->balance($referrer);
        $svc->convertOnSubscription($referred);
        $this->assertGreaterThan($before, app(CreditService::class)->balance($referrer));
        $this->assertEquals('rewarded', Referral::where('referred_id', $referred->id)->value('status'));
        // Second conversion is a no-op (idempotent).
        $svc->convertOnSubscription($referred);
        $this->assertEquals(1, Referral::where('referred_id', $referred->id)->count());
    }

    public function test_privacy_center_pause_export_sessions(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->get('/privasi')->assertOk();
        $this->actingAs($me)->post('/privasi/jeda')->assertRedirect();
        $this->assertTrue($me->fresh()->is_paused);
        // Paused users vanish from discovery.
        $seer = User::factory()->create();
        $ids = app(CandidateRetrievalService::class)->pool($seer, [])->pluck('users.id')->all();
        $this->assertNotContains($me->id, $ids);
        $this->actingAs($me)->post('/privasi/lanjut')->assertRedirect();
        $this->assertFalse($me->fresh()->is_paused);

        $export = $this->actingAs($me)->get('/privasi/ekspor')->assertOk();
        $json = $export->json();
        $this->assertArrayHasKey('user', $json);
        $this->assertArrayNotHasKey('password', $json['user']);

        \DB::table('sessions')->insert(['id' => 'sess-test-1', 'user_id' => $me->id, 'ip_address' => '1.2.3.4', 'user_agent' => 't', 'payload' => 'x', 'last_activity' => time()]);
        $this->actingAs($me)->delete('/privasi/sesi/sess-test-1')->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-test-1']);
    }

    public function test_group_invite_request_flow_and_cover(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['owner_id' => $owner->id, 'name' => 'G Uji', 'visibility' => 'private']);

        $this->actingAs($member)->post("/groups/{$group->id}/request")->assertRedirect();
        $req = GroupJoinRequest::where('group_id', $group->id)->where('user_id', $member->id)->firstOrFail();
        $this->actingAs($owner)->post("/groups/{$group->id}/requests/{$req->id}", ['action' => 'approve'])->assertRedirect();
        $this->assertTrue($group->fresh()->hasMember($member->id));

        Storage::fake('public');
        $file = UploadedFile::fake()->image('cover.jpg', 800, 400);
        $this->actingAs($owner)->post("/groups/{$group->id}/cover", ['cover' => $file])->assertRedirect();
        $this->assertNotNull($group->fresh()->cover_path);

        $invited = User::factory()->create();
        $this->actingAs($owner)->post("/groups/{$group->id}/invite", ['username' => $invited->username])->assertRedirect();
        $this->assertTrue($group->fresh()->hasMember($invited->id));
    }

    public function test_member_event_create_and_cover_upload(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->post('/events', [
            'title' => 'Kopi Darat Uji', 'city' => 'Jakarta',
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i'),
        ])->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Kopi Darat Uji', 'host_id' => $me->id, 'status' => 'published']);

        Storage::fake('public');
        $this->actingAs($me)->post('/profile/cover', ['cover' => UploadedFile::fake()->image('c.jpg', 900, 300)])->assertRedirect();
        $this->assertNotNull($me->fresh()->cover_path);
        $html = $this->actingAs($me)->get('/profile/'.$me->id)->assertOk()->getContent();
        $this->assertStringContainsString($me->fresh()->cover_path, $html);
    }

    public function test_pwa_manifest_and_call_ice(): void
    {
        $manifest = $this->get('/manifest.webmanifest')->assertOk()->json();
        $this->assertEquals('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);

        $me = User::factory()->create();
        $ice = $this->actingAs($me, 'sanctum')->getJson('/api/v1/calls/ice')->assertOk()->json();
        $this->assertNotEmpty($ice['iceServers']);
    }
}
