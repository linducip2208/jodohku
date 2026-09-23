<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPrivacyAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_photos_hidden_from_others_via_api(): void
    {
        $owner = User::factory()->create();
        $owner->photos()->create(['path' => 'private/x.jpg', 'status' => 'approved', 'is_private' => true]);
        $owner->photos()->create(['path' => 'public/y.jpg', 'status' => 'approved', 'is_private' => false]);
        $viewer = User::factory()->create();

        $paths = collect($this->actingAs($viewer)->getJson("/api/v1/profile/{$owner->id}")->assertOk()->json('photos'))->pluck('path');
        $this->assertContains('public/y.jpg', $paths);
        $this->assertNotContains('private/x.jpg', $paths);

        // Owner still sees their own private photo.
        $own = collect($this->actingAs($owner)->getJson("/api/v1/profile/{$owner->id}")->assertOk()->json('photos'))->pluck('path');
        $this->assertContains('private/x.jpg', $own);
    }

    public function test_avatar_fallback_never_leaks_private_photo(): void
    {
        $owner = User::factory()->create(['avatar_path' => null]);
        $owner->photos()->create(['path' => 'private/z.jpg', 'status' => 'approved', 'is_private' => true]);

        $this->assertNull($owner->fresh()->avatarUrl());
    }

    public function test_bio_respects_visibility_setting(): void
    {
        $owner = User::factory()->create();
        $owner->profile()->updateOrCreate([], ['bio' => 'Rahasia dapur bio']);
        $owner->profilePrivacy()->updateOrCreate([], ['bio_visibility' => 'private']);
        $viewer = User::factory()->create();

        $bio = $this->actingAs($viewer)->getJson("/api/v1/profile/{$owner->id}")->assertOk()->json('profile.bio');
        $this->assertNull($bio);

        $own = $this->actingAs($owner)->getJson("/api/v1/profile/{$owner->id}")->assertOk()->json('profile.bio');
        $this->assertEquals('Rahasia dapur bio', $own);
    }

    public function test_payment_retry_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create(['role' => 'admin']);
        $payment = Payment::create([
            'user_id' => $owner->id, 'gateway' => 'manual', 'amount' => 50000,
            'total_amount' => 50000, 'currency' => 'IDR', 'status' => 'failed',
            'invoice_number' => 'INV-TEST-001',
        ]);

        // Staff can view but must not mint retries for others.
        $this->actingAs($staff)->postJson("/api/v1/payments/{$payment->id}/retry")->assertForbidden();
        $this->assertEquals(1, Payment::where('user_id', $owner->id)->count());
    }

    public function test_health_exposes_no_secrets(): void
    {
        $body = $this->get('/health')->assertOk()->getContent();
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('secret', strtolower($body));
        $json = $this->get('/health')->json();
        $this->assertArrayHasKey('checks', $json);
        $this->assertArrayHasKey('version', $json);
    }
}
