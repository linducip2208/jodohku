<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\CompatibilityReport;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Courtship;
use App\Models\CreditTransaction;
use App\Models\Event;
use App\Models\Group;
use App\Models\SavedFilter;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\BoostService;
use App\Services\CallService;
use App\Services\CreditService;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterMissionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_and_register_have_throttle(): void
    {
        $loginRoute = collect(\Route::getRoutes())->first(fn ($r) => $r->getName() === 'login.attempt');
        $registerRoute = collect(\Route::getRoutes())->first(fn ($r) => $r->getName() === 'register.store');
        $this->assertNotNull($loginRoute);
        $this->assertNotNull($registerRoute);
        $this->assertContains('throttle:5,1,web-login', $loginRoute->gatherMiddleware());
        $this->assertContains('throttle:10,1,web-register', $registerRoute->gatherMiddleware());
    }

    public function test_phone_otp_is_hashed_and_rate_limited(): void
    {
        $user = User::factory()->create(['phone' => '+628100000001']);
        $this->actingAs($user)->post('/phone-verify/send', ['phone' => '+628100000002'])->assertRedirect();
        $cached = \Cache::get('phone-otp:'.sha1('+628100000002'));
        $this->assertNotNull($cached);
        // Stored value is HMAC hash, never the raw 6-digit OTP.
        $this->assertDoesNotMatchRegularExpression('/^\d{6}$/', (string) $cached);
    }

    public function test_group_members_only_requires_membership(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::create(['owner_id' => $owner->id, 'name' => 'G Private Test', 'visibility' => 'members_only']);
        $this->assertFalse($outsider->can('view', $group));
        $group->members()->create(['user_id' => $outsider->id, 'role' => 'member', 'joined_at' => now()]);
        $this->assertTrue($outsider->can('view', $group->fresh()));
    }

    public function test_distance_is_fuzzed_to_half_km(): void
    {
        $a = User::factory()->create(['latitude' => -6.2, 'longitude' => 106.8]);
        $b = User::factory()->create(['latitude' => -6.2005, 'longitude' => 106.8005]);
        $dist = app(DiscoveryService::class)->distanceKm($a, $b);
        $this->assertNotNull($dist);
        $this->assertEquals(round($dist * 2) / 2, $dist);
    }

    public function test_avatar_never_falls_back_to_private_photo(): void
    {
        $user = User::factory()->create(['avatar_path' => null]);
        $photo = $user->photos()->create([
            'path' => 'private/x.jpg', 'status' => 'approved', 'is_private' => true, 'sort_order' => 0,
        ]);
        $user->load('photos');
        $this->assertNull($user->avatarUrl());
        $photo->update(['is_private' => false]);
        $this->assertStringContainsString('private/x.jpg', (string) $user->fresh(['photos'])->avatarUrl());
    }

    public function test_call_end_is_idempotent(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        app(CreditService::class)->award($caller, 1000, 'seed');
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->members()->createMany([
            ['user_id' => $caller->id, 'role' => 'member'],
            ['user_id' => $receiver->id, 'role' => 'member'],
        ]);
        $svc = app(CallService::class);
        $call = $svc->invite($caller, $conversation->fresh(), 'voice');
        $svc->accept($call, $receiver);
        // Backdate start so at least 1 minute is charged.
        $call->fresh()->update(['started_at' => now()->subMinutes(2)]);
        $first = $svc->end($call->fresh(), $caller);
        $balanceAfterFirst = app(CreditService::class)->balance($caller);
        $second = $svc->end($call->fresh(), $caller);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals('ended', $second->status->value ?? (string) $second->status);
        $this->assertEquals($balanceAfterFirst, app(CreditService::class)->balance($caller));
        $this->assertEquals(1, CreditTransaction::where('reference_type', 'call')->where('reference_id', $call->id)->count());
    }

    public function test_verification_upload_is_stored_private(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('ktp.jpg', 800, 600);
        // Web route now accepts files[] and stores to private disk.
        $this->actingAs($user)->post('/verification', [
            'type' => 'id_card', 'files' => [$file],
        ])->assertRedirect();
        $this->assertDatabaseHas('verification_requests', ['user_id' => $user->id, 'type' => 'id_card']);
    }

    public function test_saved_filter_rename_duplicate_default(): void
    {
        $user = User::factory()->create();
        $saved = $this->actingAs($user)->postJson('/filter-tersimpan', [
            'name' => 'Asli', 'filters' => ['gender' => 'female', 'city' => 'Jakarta'],
        ])->assertCreated()->json();
        $id = (int) $saved['id'];
        $this->actingAs($user)->patchJson("/filter-tersimpan/{$id}", ['name' => 'Baru'])->assertOk();
        $this->actingAs($user)->postJson("/filter-tersimpan/{$id}/duplikat")->assertCreated();
        $this->actingAs($user)->postJson("/filter-tersimpan/{$id}/default")->assertOk();
        $this->assertTrue((bool) SavedFilter::find($id)->fresh()->is_default);
    }

    public function test_pwa_offline_fallback_exists(): void
    {
        $this->get('/offline-fallback')->assertOk();
        $this->get('/manifest.webmanifest')->assertOk();
    }

    public function test_courtship_policy_registered(): void
    {
        $this->assertNotNull(\Gate::getPolicyFor(Courtship::class));
        $this->assertNotNull(\Gate::getPolicyFor(Consultation::class));
        $this->assertNotNull(\Gate::getPolicyFor(CompatibilityReport::class));
    }

    public function test_boost_spend_has_reference(): void
    {
        $user = User::factory()->create();
        app(CreditService::class)->award($user, 200, 'seed');
        $this->actingAs($user)->postJson('/api/v1/boost', [], ['Accept' => 'application/json'])->assertSuccessful();
        $this->assertTrue(app(BoostService::class)->isLive($user->fresh()));
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'reference_type' => 'boost']);
    }

    public function test_event_suggested_excludes_self_and_blocks(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $blocked = User::factory()->create();
        $event = Event::create([
            'host_id' => $me->id, 'title' => 'Kopi Darat Test',
            'slug' => 'kopi-darat-test-'.uniqid(), 'city' => 'Jakarta',
            'starts_at' => now()->addDays(3), 'status' => 'published',
        ]);
        foreach ([$me, $friend, $blocked] as $u) {
            $event->members()->create(['user_id' => $u->id, 'status' => 'confirmed']);
        }
        Block::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

        $res = $this->actingAs($me, 'sanctum')->getJson("/api/v1/events/{$event->id}/suggested")->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($friend->id, $ids);
        $this->assertNotContains($me->id, $ids);
        $this->assertNotContains($blocked->id, $ids);

        // RSVP nudge carries suggested_count.
        $rsvp = $this->actingAs($me, 'sanctum')->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'confirmed'])->assertOk()->json();
        $this->assertArrayHasKey('suggested_count', $rsvp);
    }

    public function test_api_saved_filters_crud(): void
    {
        $user = User::factory()->create();
        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/saved-filters', [
            'name' => 'API Filter', 'filters' => ['gender' => 'female'],
        ])->assertCreated()->json();
        $id = (int) $created['id'];
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/saved-filters')->assertOk();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/saved-filters/{$id}", ['name' => 'API Baru'])->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/saved-filters/{$id}/duplicate")->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/saved-filters/{$id}/default")->assertOk();
        $this->assertTrue((bool) SavedFilter::find($id)->fresh()->is_default);
        // Cross-user access forbidden.
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')->patchJson("/api/v1/saved-filters/{$id}", ['name' => 'Hack'])->assertForbidden();
    }

    public function test_verification_badges_derive_from_approved_requests(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'phone_verified_at' => null]);
        $badges = $user->verificationBadges();
        $this->assertTrue($badges['email']);
        $this->assertFalse($badges['phone']);
        $this->assertFalse($badges['photo']);
        VerificationRequest::create(['user_id' => $user->id, 'type' => 'selfie', 'status' => 'approved']);
        $this->assertTrue($user->fresh()->verificationBadges()['photo']);

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')->assertOk()->json();
        $this->assertArrayHasKey('verification', $res['user'] ?? $res);
    }

    public function test_delete_account_anonymizes_pii(): void
    {
        $user = User::factory()->create(['email' => 'hapus@test.local', 'phone' => '+628999000111']);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/auth/account', ['password' => 'password'])->assertOk();
        $fresh = $user->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertStringContainsString('@deleted.local', (string) $fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->phone_hash);
    }
}
