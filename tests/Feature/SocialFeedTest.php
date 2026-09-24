<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feed_shows_composer_stories_and_posts(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['is_online' => true]);
        Post::create(['user_id' => $other->id, 'body' => 'Postingan komunitas pertama']);

        $html = $this->actingAs($me)->get('/home')->assertOk()->getContent();
        $this->assertStringContainsString('Bagikan sesuatu', $html);
        $this->assertStringContainsString('Sorotan', $html);
        $this->assertStringContainsString('Postingan komunitas pertama', $html);
        $this->assertStringContainsString('Orang untukmu', $html);
    }

    public function test_home_feed_excludes_blocked_and_hidden(): void
    {
        $me = User::factory()->create();
        $blocked = User::factory()->create();
        Block::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);
        Post::create(['user_id' => $blocked->id, 'body' => 'Postingan si terblokir unik123']);
        $hidden = Post::create(['user_id' => $me->id, 'body' => 'Postingan tersembunyi unik456']);
        $hidden->update(['is_hidden' => true]);

        $html = $this->actingAs($me)->get('/home')->assertOk()->getContent();
        $this->assertStringNotContainsString('unik123', $html);
        $this->assertStringNotContainsString('unik456', $html);
    }

    public function test_home_composer_publishes_to_feed(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->post('/komunitas', ['body' => 'Halo dari home composer'])->assertRedirect();
        $html = $this->actingAs($me)->get('/home')->assertOk()->getContent();
        $this->assertStringContainsString('Halo dari home composer', $html);
    }

    public function test_post_report_creates_moderation_record(): void
    {
        $me = User::factory()->create();
        $author = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'body' => 'Untuk dilaporkan']);

        $this->actingAs($me)->post("/komunitas/postingan/{$post->id}/laporkan")->assertRedirect();
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $me->id,
            'reported_user_id' => $author->id,
            'reportable_id' => $post->id,
            'status' => 'pending',
        ]);
    }

    public function test_profile_shows_own_public_posts_only(): void
    {
        $me = User::factory()->create();
        Post::create(['user_id' => $me->id, 'body' => 'Postingan publikku unik789']);
        $other = User::factory()->create();
        $html = $this->actingAs($me)->get('/profile/'.$me->id)->assertOk()->getContent();
        $this->assertStringContainsString('Postingan', $html);
        $this->assertStringContainsString('unik789', $html);

        // Stranger's members-only post stays hidden.
        $stranger = User::factory()->create();
        Post::create(['user_id' => $stranger->id, 'body' => 'Semi privat unik000', 'visibility' => 'members_only']);
        // Public post still visible.
        $pub = Post::create(['user_id' => $stranger->id, 'body' => 'Publik unik111']);
        $html = $this->actingAs($me)->get('/profile/'.$stranger->id)->assertOk()->getContent();
        $this->assertStringContainsString('unik111', $html);
        $this->assertStringNotContainsString('unik000', $html);
    }

    public function test_discover_has_compact_filter_sheet(): void
    {
        $me = User::factory()->create();
        $html = $this->actingAs($me)->get('/discover')->assertOk()->getContent();
        $this->assertStringContainsString('Untuk Anda', $html);
        $this->assertStringContainsString('Filter', $html);
        $this->assertStringContainsString('Orang di sekitarmu', $html);
    }
}
