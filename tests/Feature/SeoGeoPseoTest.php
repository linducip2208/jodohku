<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoGeoPseoTest extends TestCase
{
    use RefreshDatabase;

    protected function ldJson(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $out = [];
        foreach ($m[1] as $raw) {
            $decoded = json_decode($raw, true);
            $this->assertNotNull($decoded, 'JSON-LD must be valid JSON');
            $out[] = $decoded;
        }

        return $out;
    }

    public function test_homepage_has_canonical_og_and_schemas(): void
    {
        $res = $this->get('/');
        $res->assertOk();
        $html = $res->getContent();
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('name="twitter:card"', $html);
        $types = array_column($this->ldJson($html), '@type');
        $this->assertContains('WebSite', $types);
        $this->assertContains('Organization', $types);
        $this->assertContains('FAQPage', $types);
    }

    public function test_auth_pages_are_noindex(): void
    {
        foreach (['/login', '/register'] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();
            $this->assertStringContainsString('noindex', $html, $uri);
        }
    }

    public function test_private_member_pages_require_auth(): void
    {
        foreach (['/discover', '/matches', '/chat', '/settings', '/notifications'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_robots_blocks_private_and_lists_sitemap(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['Disallow: /admin', 'Disallow: /api/', 'Disallow: /chat', 'Disallow: /login'] as $rule) {
            $this->assertStringContainsString($rule, $body);
        }
        $this->assertStringContainsString('Sitemap:', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }

    public function test_sitemap_excludes_private_content(): void
    {
        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('sitemap-pages.xml', $index);
        $this->assertStringNotContainsString('/blog', $index);
        $pages = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/biro-jodoh', $pages);
        $this->assertStringContainsString('/taaruf', $pages);
        foreach (['/chat', '/admin', '/api/', '/login', '/matches'] as $private) {
            $this->assertStringNotContainsString($private, $pages);
        }
    }

    public function test_pseo_hub_and_city_quality_gate(): void
    {
        $this->get('/biro-jodoh')->assertOk()
            ->assertSee('Biro Jodoh Indonesia', false);
        // Unknown city: 404, never thin filler.
        $this->get('/biro-jodoh/atlantis')->assertNotFound();
        // Known city with zero members: 404 (quality gate).
        $this->get('/biro-jodoh/jakarta')->assertNotFound();

        User::factory()->count(12)->create(['city' => 'Jakarta', 'status' => 'active']);
        Cache::forget('seo:pseo:cities');
        $html = $this->get('/biro-jodoh/jakarta')->assertOk()->getContent();
        $this->assertStringContainsString('Biro Jodoh Jakarta', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $types = array_column($this->ldJson($html), '@type');
        $this->assertContains('BreadcrumbList', $types);
        $this->assertContains('FAQPage', $types);
        // Internal linking present.
        $this->assertStringContainsString('/biro-jodoh/tangerang', $html);
        $this->assertStringContainsString('/panduan/', $html);
    }

    public function test_taaruf_and_topic_pages(): void
    {
        $html = $this->get('/taaruf')->assertOk()->getContent();
        $this->assertStringContainsString('Smart Taaruf', $html);
        $this->assertContains('FAQPage', array_column($this->ldJson($html), '@type'));

        $html = $this->get('/panduan/cara-taaruf')->assertOk()->getContent();
        $this->assertStringContainsString('rel="canonical"', $html);
        $types = array_column($this->ldJson($html), '@type');
        $this->assertContains('BlogPosting', $types);
        $this->assertContains('FAQPage', $types);
        $this->get('/panduan/topik-tidak-ada')->assertNotFound();
    }

    public function test_public_profile_opt_in_only(): void
    {
        $user = User::factory()->create(['username' => 'seooptin001']);
        // Default: not indexed → 404 (no enumeration signal difference).
        $this->get('/u/seooptin001')->assertNotFound();

        $user->profilePrivacy()->updateOrCreate([], ['is_public_index' => true]);
        $html = $this->get('/u/seooptin001')->assertOk()->getContent();
        $this->assertStringContainsString($user->displayName(), $html);
        $this->assertStringNotContainsString($user->email, $html);
        $this->assertContains('ProfilePage', array_column($this->ldJson($html), '@type'));

        // Incognito revokes eligibility even when opted in.
        $user->profilePrivacy()->update(['is_incognito' => true]);
        $this->get('/u/seooptin001')->assertNotFound();
    }

    public function test_sitemap_profiles_only_opted_in(): void
    {
        $hidden = User::factory()->create(['username' => 'seohidden001']);
        $shown = User::factory()->create(['username' => 'seoshown001']);
        $shown->profilePrivacy()->updateOrCreate([], ['is_public_index' => true]);

        $xml = $this->get('/sitemap-profiles.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/u/seoshown001', $xml);
        $this->assertStringNotContainsString('/u/seohidden001', $xml);
        $this->assertStringNotContainsString($shown->email, $xml);
    }

    public function test_trailing_slash_redirects_to_canonical(): void
    {
        // Note: the $this->get() test client trims trailing slashes itself,
        // so trailing-slash behavior is asserted through the kernel directly.
        $kernel = app(Kernel::class);
        foreach (['/taaruf/', '/biro-jodoh/'] as $uri) {
            $res = $kernel->handle(Request::create($uri, 'GET'));
            $this->assertTrue(in_array($res->getStatusCode(), [301, 308]), $uri);
            $this->assertStringEndsWith(rtrim($uri, '/'), (string) $res->headers->get('Location'));
        }
    }

    public function test_canonical_strips_tracking_params(): void
    {
        $html = $this->get('/taaruf?utm_source=google&gclid=abc')->assertOk()->getContent();
        preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);
        $this->assertArrayHasKey(1, $m);
        $this->assertStringNotContainsString('utm_source', $m[1]);
        $this->assertStringNotContainsString('gclid', $m[1]);
        $this->assertStringEndsWith('/taaruf', $m[1]);
    }
}
