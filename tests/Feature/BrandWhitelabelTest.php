<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;
use App\Services\BrandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
