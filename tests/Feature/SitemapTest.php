<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Forum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_lists_public_content_only(): void
    {
        BlogPost::create([
            'title' => 'Sitemap Post', 'slug' => 'sitemap-post',
            'body' => 'Body', 'status' => 'published', 'published_at' => now(),
        ]);
        Forum::create(['name' => 'F', 'slug' => 'f']);

        // Sitemap is now an index of public sections only. Auth-gated
        // blog/forum URLs must never be advertised to crawlers.
        $res = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $res->headers->get('Content-Type'));
        $res->assertSee('sitemap-pages.xml', false);
        $res->assertDontSee('sitemap-post', false);
        $res->assertDontSee('/forums/f', false);
        $res->assertDontSee('/blog', false);
        $res->assertDontSee('/chat', false);
        $res->assertDontSee('/admin', false);

        $pages = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/biro-jodoh', $pages);
        $this->assertStringContainsString('/taaruf', $pages);
        $this->assertStringContainsString('/guidelines', $pages);
    }
}
