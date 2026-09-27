<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every feature must be reachable: no orphan pages. If a route exists,
 * some UI entry point must link to it (sidebar, pills, CTA, footer).
 */
class FeatureConnectivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sidebar_reaches_core_features(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();
        foreach (['/discover', '/matches', '/dates', '/chat', '/likes', '/events', '/komunitas', '/notifications', '/safety', '/premium', '/settings'] as $path) {
            $this->assertStringContainsString('"'.$path.'"', $html, "Missing nav link: {$path}");
        }
    }

    public function test_monetization_hub_links(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/premium')->assertOk()->getContent();
        foreach (['/gifts', '/boosts', '/credits', '/referral', '/harga'] as $path) {
            $this->assertStringContainsString($path, $html, "Missing monetization link: {$path}");
        }
        $landing = $this->get('/harga')->assertOk()->getContent();
        $this->assertStringContainsString('/premium', $landing);
    }

    public function test_safety_privacy_cross_links(): void
    {
        $user = User::factory()->create();
        $safety = $this->actingAs($user)->get('/safety')->assertOk()->getContent();
        foreach (['/kontak-blokir', '/privasi', '/verification'] as $path) {
            $this->assertStringContainsString($path, $safety, "Missing safety link: {$path}");
        }
        $privacy = $this->actingAs($user)->get('/privasi')->assertOk()->getContent();
        $this->assertStringContainsString('/passport', $privacy);
    }

    public function test_match_moment_ctas(): void
    {
        // Both match modals must offer chat + date next actions.
        // Assert on blade source (rendering needs full Livewire state).
        foreach (['livewire.like-buttons', 'livewire.swipe-deck'] as $view) {
            $src = file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php'));
            $this->assertStringContainsString('/chat', $src, "Missing chat CTA in {$view}");
            $this->assertStringContainsString('/dates?partner=', $src, "Missing date CTA in {$view}");
        }
    }

    public function test_profile_edit_links_questionnaire(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/profile/edit')->assertOk()->getContent();
        $this->assertStringContainsString('/questionnaire', $html);
    }
}
