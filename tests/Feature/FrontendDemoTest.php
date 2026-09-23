<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Courtship;
use App\Models\Post;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function demoUser(array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['is_demo' => true], $attrs));
        $u->profile()->updateOrCreate([], ['headline' => 'Demo headline']);
        $u->photos()->create(['path' => 'demo/x.jpg', 'status' => 'approved', 'is_private' => false]);

        return $u->fresh();
    }

    public function test_landing_showcases_demo_members_only(): void
    {
        $demo = $this->demoUser(['display_name' => 'DemoShowcase']);
        $real = User::factory()->create(['display_name' => 'RealPerson', 'is_demo' => false]);
        $real->profile()->updateOrCreate([], ['headline' => 'Real']);
        $real->photos()->create(['path' => 'real/y.jpg', 'status' => 'approved', 'is_private' => false]);
        $hidden = $this->demoUser(['display_name' => 'HiddenDemo']);
        $hidden->photos()->where('path', 'demo/x.jpg')->update(['is_private' => true]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('DemoShowcase', $html);
        $this->assertStringContainsString('Lihat Demo Member', $html);
        $this->assertStringNotContainsString('RealPerson', $html);
        $this->assertStringNotContainsString('HiddenDemo', $html);
        // No emails or private paths leak into public HTML.
        $this->assertStringNotContainsString($demo->email, $html);
        $this->assertStringNotContainsString($real->email, $html);
    }

    public function test_landing_uses_real_plans_and_stats(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('Rp49rb', $html);
        $this->assertStringNotContainsString('2,4 jt+', $html);
        $this->assertStringNotContainsString('380rb', $html);
    }

    public function test_community_feed_privacy_and_moderation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        Block::create(['blocker_id' => $a->id, 'blocked_id' => $c->id]);

        $visible = Post::create(['user_id' => $b->id, 'body' => 'Halo komunitas']);
        Post::create(['user_id' => $c->id, 'body' => 'Dari yang diblokir']);
        $hidden = Post::create(['user_id' => $b->id, 'body' => 'Disembunyikan']);
        $hidden->update(['is_hidden' => true]);

        $html = $this->actingAs($a)->get('/komunitas')->assertOk()->getContent();
        $this->assertStringContainsString('Halo komunitas', $html);
        $this->assertStringNotContainsString('Dari yang diblokir', $html);
        $this->assertStringNotContainsString('Disembunyikan', $html);

        // Post + like + comment flow.
        $this->actingAs($a)->post('/komunitas', ['body' => 'Cerita taarufku'])->assertRedirect();
        $this->assertDatabaseHas('posts', ['user_id' => $a->id, 'body' => 'Cerita taarufku']);
        $post = Post::where('body', 'Cerita taarufku')->firstOrFail();
        $this->actingAs($b)->post("/komunitas/{$post->id}/like")->assertRedirect();
        $this->assertEquals(1, $post->fresh()->likes_count);
        $this->actingAs($b)->post("/komunitas/{$post->id}/komentar", ['body' => 'Semangat!'])->assertRedirect();
        $this->assertEquals(1, $post->fresh()->comments_count);

        // Others cannot delete my post.
        $this->actingAs($b)->delete("/komunitas/{$post->id}")->assertForbidden();
        $this->actingAs($a)->delete("/komunitas/{$post->id}")->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_start_taaruf_from_match(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);

        $this->actingAs($a)->post('/biro-jodoh/taaruf/mulai', ['partner_id' => $b->id])
            ->assertRedirect();
        $this->assertDatabaseHas('courtships', ['initiator_id' => $a->id, 'partner_id' => $b->id, 'status' => 'active']);

        // Stranger without match cannot start.
        $s = User::factory()->create();
        $this->actingAs($s)->post('/biro-jodoh/taaruf/mulai', ['partner_id' => $b->id])
            ->assertRedirect();
        $this->assertEquals(1, Courtship::count());
    }

    public function test_discover_accepts_landing_filters(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me)->get('/discover?gender=female&min_age=25&max_age=35&city=Jakarta')
            ->assertOk();
    }
}
