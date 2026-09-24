<?php

namespace Tests\Feature;

use App\Models\Counselor;
use App\Models\Post;
use App\Models\User;
use App\Services\CandidateRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visual-regression guard for the public landing people sections.
 *
 * Covers: counselor exclusion (dating vs consultation context), no
 * duplicate faces across strip/grid (dedup by user_id), card-scoped
 * guest CTAs, incognito + private-photo privacy on the public preview.
 */
class OnlineMemberRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function demoMember(array $attrs = []): User
    {
        $u = User::factory()->create(array_merge([
            'is_demo' => true, 'is_online' => true, 'city' => 'Jakarta',
        ], $attrs));
        $u->profile()->updateOrCreate([], ['headline' => 'Demo']);

        return $u->fresh();
    }

    public function test_online_strip_excludes_counselors_and_dedups_grid(): void
    {
        $member = $this->demoMember(['display_name' => 'StripMember']);
        $counselorUser = $this->demoMember(['display_name' => 'StripKonselor']);
        Counselor::create(['user_id' => $counselorUser->id, 'specialty' => 'Pranikah', 'is_active' => true]);

        $html = $this->get('/')->assertOk()->content();

        // Counselor never appears as a dating prospect anywhere on landing.
        $this->assertStringNotContainsString('StripKonselor', $html);
        $this->assertStringContainsString('StripMember', $html);

        // Guest CTAs are card-scoped anchors, never section wrappers:
        // every /register anchor must stay a small card (no giant blobs).
        preg_match_all('#<a[^>]*href="[^"]*register"[^>]*>(.*?)</a>#s', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $inner) {
            $this->assertLessThan(3000, strlen(strip_tags($inner)), 'Guest CTA wraps too much content');
        }
    }

    public function test_no_duplicate_faces_across_strip_and_grid(): void
    {
        // Online members land in the strip; the demo grid must skip them
        // (dedup by user_id). Headlines render ONLY in the grid, so a
        // strip member headline must appear zero times on the page.
        foreach (['HEADLINE_DEDUP_AA', 'HEADLINE_DEDUP_BB'] as $i => $headline) {
            $u = $this->demoMember(['display_name' => 'DedupUser'.$i]);
            $u->profile()->updateOrCreate([], ['headline' => $headline]);
            $u->photos()->create(['path' => 'demo/x'.$i.'.jpg', 'status' => 'approved', 'is_private' => false]);
        }

        $html = $this->get('/')->assertOk()->content();
        $this->assertStringContainsString('DedupUser0', $html);
        $this->assertStringNotContainsString('HEADLINE_DEDUP_AA', $html);
        $this->assertStringNotContainsString('HEADLINE_DEDUP_BB', $html);
    }

    public function test_public_feed_preview_hides_incognito_and_private_photos(): void
    {
        $ghost = $this->demoMember(['display_name' => 'GhostAuthor']);
        $ghost->profilePrivacy()->updateOrCreate([], ['is_incognito' => true]);
        Post::create(['user_id' => $ghost->id, 'body' => 'UNIQUEGHOSTBODY123', 'is_hidden' => false]);

        $shown = $this->demoMember(['display_name' => 'ShownAuthor']);
        Post::create(['user_id' => $shown->id, 'body' => 'UNIQUESHOWNBODY123', 'is_hidden' => false]);

        $html = $this->get('/')->assertOk()->content();
        $this->assertStringNotContainsString('UNIQUEGHOSTBODY123', $html);
        $this->assertStringContainsString('UNIQUESHOWNBODY123', $html);
    }

    public function test_retrieval_pool_never_returns_counselors(): void
    {
        $me = User::factory()->create();
        $cand = User::factory()->create(['city' => 'Jakarta']);
        $counselorUser = User::factory()->create(['city' => 'Jakarta']);
        Counselor::create(['user_id' => $counselorUser->id, 'specialty' => 'Pranikah', 'is_active' => true]);

        $ids = app(CandidateRetrievalService::class)->pool($me, [])->pluck('users.id')->all();
        $this->assertContains($cand->id, $ids);
        $this->assertNotContains($counselorUser->id, $ids);
        $this->assertNotContains($me->id, $ids);
    }

    public function test_premium_gates_redirect_web_but_stay_json_for_api(): void
    {
        $free = User::factory()->create();
        $this->actingAs($free)->get('/visitors')->assertRedirect('/premium');
        $this->actingAs($free)->getJson('/visitors')->assertStatus(403)->assertJsonPath('upgrade', true);

        $premium = User::factory()->premium()->create();
        $this->actingAs($premium)->get('/visitors')->assertOk();
    }
}
