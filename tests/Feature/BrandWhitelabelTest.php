<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\BoostService;
use App\Services\BrandService;
use App\Services\GiftService;
use App\Services\MembershipService;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BrandWhitelabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_theme_without_brands(): void
    {
        $theme = app(BrandService::class)->theme();
        $this->assertSame('Jodohku', $theme['name']);
        $this->assertSame('#f43f5e', $theme['primary']);
        $this->assertNull($theme['logo']);

        // Landing renders default brand identically.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Jodohku', $html);
    }

    public function test_admin_crud_brand(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $logo = UploadedFile::fake()->image('logo.png', 400, 120);

        $created = $this->actingAs($admin)->post('/admin/brands', [
            'name' => 'CintaKita', 'tagline' => 'Temukan cintamu',
            'primary_color' => '#0ea5e9', 'secondary_color' => '#8b5cf6',
            'domain' => 'cintakita.test', 'logo' => $logo,
            'is_active' => true, 'is_default' => true,
            'features' => ['taaruf' => false],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $brand = Brand::where('slug', 'cintakita')->firstOrFail();
        $this->assertSame('#0ea5e9', $brand->primary_color);
        $this->assertNotNull($brand->logo_path);
        $this->assertFalse($brand->featureOn('taaruf'));
        $this->assertTrue($brand->featureOn('community'));

        // Manifest follows the brand.
        $manifest = $this->get('/manifest.webmanifest')->assertOk()->json();
        $this->assertStringContainsString('CintaKita', $manifest['name']);
        $this->assertSame('#0ea5e9', $manifest['theme_color']);

        // Invalid hex rejected.
        $this->actingAs($admin)->post('/admin/brands', [
            'name' => 'Jelek', 'primary_color' => 'red',
        ])->assertSessionHasErrors('primary_color');
        unset($created);
    }

    public function test_domain_resolution_and_member_gate(): void
    {
        $brand = Brand::create([
            'slug' => 'b1', 'name' => 'Brand Satu', 'primary_color' => '#111111',
            'secondary_color' => '#222222', 'domain' => 'satu.test',
            'is_active' => true, 'is_default' => true,
            'features' => ['events' => false, 'taaruf' => false],
        ]);
        // Domain match wins.
        $viaDomain = app(BrandService::class)->current('satu.test');
        $this->assertSame($brand->id, $viaDomain->id);
        // Unknown host falls back to default.
        $this->assertSame($brand->id, app(BrandService::class)->current('lain.test')->id);

        // Member sidebar hides disabled features.
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();
        $this->assertStringNotContainsString('> Events<', $html);
        $this->assertStringNotContainsString('> Taaruf<', $html);
        $this->assertStringContainsString('> Komunitas<', $html);
    }

    public function test_export_import_roundtrip(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('brands/x/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $brand = Brand::create([
            'slug' => 'x', 'name' => 'Ex', 'primary_color' => '#123456',
            'secondary_color' => '#654321', 'logo_path' => 'brands/x/logo.png',
            'is_active' => true, 'features' => ['gifts' => false],
        ]);
        $svc = app(BrandService::class);
        $zip = $svc->exportPackage($brand);
        $this->assertFileExists($zip);

        $brand->delete();
        $this->assertDatabaseMissing('brands', ['slug' => 'x']);
        $restored = $svc->importPackage($zip, true);
        $this->assertSame('Ex', $restored->name);
        $this->assertTrue($restored->is_default);
        $this->assertNotNull($restored->logo_path);
        @unlink($zip);
    }

    public function test_wizard_draft_preview(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get('/admin/brands/wizard')->assertOk();
        $this->actingAs($admin)->postJson('/admin/brands/wizard/draft', [
            'name' => 'Draf Cinta', 'primary_color' => '#00aa00',
        ])->assertOk()->json();
        $html = $this->actingAs($admin)->get('/?preview_brand=draft')->assertOk()->getContent();
        $this->assertStringContainsString('Draf Cinta', $html);
    }

    public function test_brand_feature_flag_enforced_backend(): void
    {
        $brand = Brand::create([
            'slug' => 'g1', 'name' => 'Gated', 'primary_color' => '#111111',
            'secondary_color' => '#222222', 'is_active' => true, 'is_default' => true,
            'features' => ['events' => false, 'community' => false, 'taaruf' => false, 'counselor' => false],
        ]);
        $user = User::factory()->create();
        // Nav hides + backend 404s (previously only hidden).
        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();
        $this->assertStringNotContainsString('> Events<', $html);
        $this->actingAs($user)->get('/events')->assertNotFound();
        $this->actingAs($user)->get('/komunitas')->assertNotFound();
        $this->actingAs($user)->get('/biro-jodoh/taaruf')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/events')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/courtships')->assertNotFound();
        unset($brand);
    }

    public function test_registration_attributes_brand(): void
    {
        $brand = Brand::create([
            'slug' => 'r1', 'name' => 'Reg', 'primary_color' => '#111111',
            'secondary_color' => '#222222', 'domain' => 'reg.test',
            'is_active' => true, 'is_default' => true,
        ]);
        $this->post('/register', [
            'name' => 'Brand User', 'email' => 'branduser@test.local',
            'date_of_birth' => '1995-01-01', 'gender' => 'male',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/home');
        $this->assertSame($brand->id, User::where('email', 'branduser@test.local')->value('brand_id'));
    }

    public function test_client_scoped_to_own_brand(): void
    {
        $mine = Brand::create(['slug' => 'm1', 'name' => 'Mine', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true]);
        $theirs = Brand::create(['slug' => 't1', 'name' => 'Theirs', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true]);
        $client = User::factory()->create(['role' => UserRole::Client, 'brand_id' => $mine->id]);

        // Index shows only own.
        $list = $this->actingAs($client)->get('/admin/brands')->assertOk()->getContent();
        $this->assertStringContainsString('Mine', $list);
        $this->assertStringNotContainsString('Theirs', $list);
        // Own edit OK, theirs forbidden.
        $this->actingAs($client)->get("/admin/brands/{$mine->id}/edit")->assertOk();
        $this->actingAs($client)->get("/admin/brands/{$theirs->id}/edit")->assertForbidden();
        // Client cannot create/delete.
        $this->actingAs($client)->get('/admin/brands/wizard')->assertForbidden();
        $this->actingAs($client)->delete("/admin/brands/{$mine->id}")->assertForbidden();
        // Unbound client gets nothing.
        $lone = User::factory()->create(['role' => UserRole::Client]);
        $this->actingAs($lone)->get('/admin/brands')->assertForbidden();
    }

    public function test_brand_stats_scoped_per_brand(): void
    {
        $a = Brand::create(['slug' => 'sa', 'name' => 'SA', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true]);
        $b = Brand::create(['slug' => 'sb', 'name' => 'SB', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true]);
        User::factory()->count(3)->create(['brand_id' => $a->id]);
        User::factory()->count(1)->create(['brand_id' => $b->id]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $json = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/brands/{$a->id}/stats")->assertOk()->json();
        $this->assertSame(3, $json['users_total']);
        $client = User::factory()->create(['role' => UserRole::Client, 'brand_id' => $a->id]);
        $this->actingAs($client)->get("/admin/brands/{$a->id}/stats")->assertOk();
        $this->actingAs($client)->get("/admin/brands/{$b->id}/stats")->assertForbidden();
        $this->actingAs($client, 'sanctum')->getJson("/api/v1/admin/brands/{$b->id}/stats")->assertForbidden();
    }

    public function test_brand_templates_icons_license_hero(): void
    {
        $this->assertCount(5, BrandService::TEMPLATES);
        $this->assertArrayHasKey('islami', BrandService::TEMPLATES);

        Brand::create(['slug' => 'ex', 'name' => 'Expired', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true, 'is_default' => true, 'expires_at' => now()->subDay()]);
        $this->assertNull(app(BrandService::class)->current('anything.test'));

        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $logo = UploadedFile::fake()->image('logo.png', 400, 120);
        $this->actingAs($admin)->post('/admin/brands', [
            'name' => 'IconTest', 'primary_color' => '#0ea5e9', 'is_active' => true,
            'logo' => $logo,
        ])->assertRedirect();
        $brand = Brand::where('slug', 'icontest')->firstOrFail();
        Storage::disk('public')->assertExists('brands/icontest/icons/icon-512.png');
        Storage::disk('public')->assertExists('brands/icontest/icons/icon-maskable.png');

        $brand->update(['content' => ['hero_title' => 'Cinta Sejati Dimulai']]);
        $html = $this->actingAs($admin)->get("/?preview_brand={$brand->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Cinta Sejati Dimulai', $html);
    }

    public function test_domain_verification_and_strict_mode(): void
    {
        $brand = Brand::create(['slug' => 'dv', 'name' => 'DV', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'domain' => 'dv.test', 'is_active' => true]);
        $svc = app(BrandService::class);
        $token = $svc->verificationToken($brand);
        $this->assertNotEmpty($token);
        $this->assertNull($brand->fresh()->domain_verified_at);
        $this->assertTrue(Brand::where('domain', 'dv.test')->exists());

        // Well-known file serves the token for that host.
        // NOTE: test URLs are built from the booted UrlGenerator, so force
        // the root host for host-dependent routes.
        URL::forceRootUrl('http://dv.test');
        $res = $this->get('/.well-known/brand-verification.txt');
        $this->assertSame(200, $res->status());
        $this->assertStringContainsString($token, (string) $res->getContent());

        // HTTP verification passes with faked HTTP (DNS fails locally).
        Http::fake(['http://dv.test/*' => Http::response($token, 200)]);
        $this->assertTrue($svc->verifyDomain($brand->fresh()));
        $this->assertNotNull($brand->fresh()->domain_verified_at);

        // Strict mode: unverified domains don't resolve.
        config(['brands.require_verification' => true]);
        $unv = Brand::create(['slug' => 'uv', 'name' => 'UV', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'domain' => 'uv.test', 'is_active' => true]);
        $svc->verificationToken($unv);
        $this->assertNull($svc->current('uv.test'));
        config(['brands.require_verification' => false]);
    }

    public function test_max_users_enforced_at_registration(): void
    {
        $brand = Brand::create(['slug' => 'mq', 'name' => 'MQ', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'domain' => 'mq.test', 'is_active' => true, 'is_default' => true, 'max_users' => 1]);
        User::factory()->create(['brand_id' => $brand->id]);
        $this->assertSame(1, User::where('brand_id', $brand->id)->count());
        $this->expectException(HttpException::class);
        app(BrandService::class)->assertRegistrationOpen($brand->id);
    }

    public function test_gifts_boost_flags_enforced_in_services(): void
    {
        Brand::create(['slug' => 'gb', 'name' => 'GB', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true, 'is_default' => true, 'features' => ['gifts' => false, 'boost' => false]]);
        $this->assertFalse(app(BrandService::class)->featureEnabled('gifts'));
        $this->assertFalse(app(BrandService::class)->featureEnabled('boost'));

        $a = User::factory()->create();
        $b = User::factory()->create();
        try {
            app(GiftService::class)->send($a, $b, 'rose');
            $this->fail('Gift harus ditolak saat flag mati.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
        }
        try {
            app(BoostService::class)->activate($a);
            $this->fail('Boost harus ditolak saat flag mati.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('disabled', $e->getMessage());
        }
    }

    public function test_brand_mail_sender_applied(): void
    {
        $brand = Brand::create(['slug' => 'ml', 'name' => 'ML', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true, 'is_default' => true, 'mail_from_address' => 'halo@ml.test', 'mail_from_name' => 'ML Team']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        // Invalid email rejected at admin form.
        $this->actingAs($admin)->put("/admin/brands/{$brand->id}", [
            'name' => 'ML', 'mail_from_address' => 'bukan-email',
        ])->assertSessionHasErrors('mail_from_address');
        // Middleware applies sender on web requests.
        $this->actingAs($admin)->get('/admin/brands')->assertOk();
        $this->assertSame('halo@ml.test', config('mail.from.address'));
    }

    public function test_brand_scoped_plans_and_public_pricing(): void
    {
        $brand = Brand::create(['slug' => 'pl', 'name' => 'PL', 'primary_color' => '#111111', 'secondary_color' => '#222222', 'is_active' => true, 'is_default' => true]);
        MembershipPlan::create(['brand_id' => $brand->id, 'code' => 'pl_premium', 'name' => 'PL Premium', 'price' => 49000, 'is_active' => true]);
        MembershipPlan::create(['code' => 'global_basic', 'name' => 'Global Basic', 'price' => 29000, 'is_active' => true]);

        $codes = app(MembershipService::class)->plans()->pluck('code')->all();
        $this->assertContains('pl_premium', $codes);
        $this->assertNotContains('global_basic', $codes);
        $this->assertSame('PL Premium', app(MembershipService::class)->findOrFail('pl_premium')->name);
        try {
            app(MembershipService::class)->findOrFail('global_basic');
            $this->fail('Global plan harus tak terlihat di brand ber-katalog.');
        } catch (ModelNotFoundException) {
        }
        $html = $this->get('/harga')->assertOk()->getContent();
        $this->assertStringContainsString('PL Premium', $html);
        $this->assertStringContainsString('49.000', $html);
    }

    public function test_premium_trial_once_and_expiry(): void
    {
        MembershipPlan::create(['code' => 'trial_plan', 'name' => 'Trial Plan', 'price' => 99000, 'is_active' => true]);
        $user = User::factory()->create();
        $svc = app(SubscriptionService::class);

        $this->assertTrue($svc->trialEligible($user));
        $sub = $svc->startTrial($user);
        $this->assertSame('trialing', $sub->status->value ?? (string) $sub->status);
        $this->assertTrue($user->fresh()->isPremium());
        $this->assertFalse($svc->trialEligible($user->fresh()));
        try {
            $svc->startTrial($user->fresh());
            $this->fail('Trial kedua harus ditolak.');
        } catch (\RuntimeException) {
        }
        $sub->update(['trial_ends_at' => now()->subMinute()]);
        $this->assertSame(1, $svc->expireDue());
        $this->assertFalse($user->fresh()->isPremium());

        $fresh = User::factory()->create();
        $this->actingAs($fresh, 'sanctum')->postJson('/api/v1/trial')->assertCreated();
        $this->actingAs($fresh, 'sanctum')->postJson('/api/v1/trial')->assertStatus(422);
    }
}
