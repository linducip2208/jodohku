<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing community preview is a preview, never a full feed:
 * max 3 compact cards (desktop grid / mobile carousel), no forms,
 * truncated bodies, hero-deduped, linking out — never inlining —
 * community interaction.
 */
class CommunityPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function author(): User
    {
        $u = User::factory()->create(['is_demo' => true, 'city' => 'Jakarta']);
        $u->profile()->updateOrCreate([], ['headline' => 'Demo']);

        return $u;
    }

    /** @return array{html:string, section:string} */
    protected function landing(): array
    {
        $html = $this->get('/')->assertOk()->content();
        $start = strpos($html, 'community-preview-title');
        $end = $start === false ? 0 : strpos($html, '</section>', $start);

        return ['html' => $html, 'section' => $start === false ? '' : substr($html, $start, $end - $start)];
    }

    public function test_preview_has_max_three_compact_cards(): void
    {
        $author = $this->author();
        for ($i = 0; $i < 6; $i++) {
            Post::create(['user_id' => $author->id, 'body' => 'Preview body number '.$i.' with filler text to be long enough.', 'is_hidden' => false]);
        }

        $page = $this->landing();
        // 1 hero post + max 3 preview cards (never 6 stacked).
        // NOTE: count article tags (the <style> block also mentions the class).
        $this->assertEquals(3, substr_count($page['html'], '<article role="listitem" class="ld-card ld-preview-card"'));
    }

    public function test_preview_renders_no_forms_or_composers(): void
    {
        $author = $this->author();
        Post::create(['user_id' => $author->id, 'body' => 'Form check body one.', 'is_hidden' => false]);
        Post::create(['user_id' => $author->id, 'body' => 'Form check body two.', 'is_hidden' => false]);

        $page = $this->landing();
        $this->assertNotEmpty($page['section']);
        $this->assertStringNotContainsString('<form', $page['section']);
        $this->assertStringNotContainsString('textarea', $page['section']);
        $this->assertStringNotContainsString('komentar" method', $page['section']);
    }

    public function test_preview_truncates_long_body_and_dedups_hero(): void
    {
        $author = $this->author();
        $long = 'HEROBODY '.str_repeat('kata ', 40);
        $long2 = 'PREVIEWBODY '.str_repeat('kata ', 40);
        Post::create(['user_id' => $author->id, 'body' => $long, 'is_hidden' => false]);
        Post::create(['user_id' => $author->id, 'body' => $long2, 'is_hidden' => false]);

        $page = $this->landing();
        // Hero shows the newest post; preview skips it (no repeat faces/bodies).
        $this->assertStringContainsString(substr($long2, 0, 60), $page['html']);
        $this->assertStringNotContainsString($long2, $page['section']);
        $this->assertStringNotContainsString($long, $page['section']);
    }

    public function test_preview_links_out_for_guests_and_members(): void
    {
        $author = $this->author();
        Post::create(['user_id' => $author->id, 'body' => 'Link check body one.', 'is_hidden' => false]);
        Post::create(['user_id' => $author->id, 'body' => 'Link check body two.', 'is_hidden' => false]);

        $guest = $this->landing();
        $this->assertStringContainsString('Lihat semua →', $guest['section']);
        $this->assertStringContainsString('Ikut ngobrol — daftar gratis', $guest['html']);

        $member = User::factory()->create();
        $authed = $this->actingAs($member)->get('/')->assertOk()->content();
        $this->assertStringContainsString('href="/komunitas"', $authed);
    }
}
