<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_lists_public_content_only(): void
    {
        \App\Models\BlogPost::create([
            'title' => 'Sitemap Post', 'slug' => 'sitemap-post',
            'body' => 'Body', 'status' => 'published', 'published_at' => now(),
        ]);
        \App\Models\Forum::create(['name' => 'F', 'slug' => 'f']);

        $res = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $res->headers->get('Content-Type'));
        $res->assertSee('sitemap-post', false);
        $res->assertSee('/forums/f', false);
        $res->assertDontSee('/chat', false);
        $res->assertDontSee('/admin', false);
    }
}
