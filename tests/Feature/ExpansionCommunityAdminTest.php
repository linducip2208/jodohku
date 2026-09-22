<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Forum;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpansionCommunityAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_forum_thread_records_views_and_trending(): void
    {
        $user = User::factory()->create();
        $forum = Forum::create(['name' => 'Kenalan', 'slug' => 'kenalan', 'is_active' => true, 'sort_order' => 1]);
        $thread = ForumThread::create([
            'forum_id' => $forum->id, 'user_id' => $user->id,
            'title' => 'Perkenalan pertama', 'body' => 'Halo semua', 'last_reply_at' => now(),
        ]);

        $this->assertEquals(0, $thread->views_count);
        $thread->recordView();
        $thread->recordView();
        $this->assertEquals(2, $thread->fresh()->views_count);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/forums/trending')
            ->assertOk();
        $data = $response->json('threads');
        $titles = array_column($data, 'title');
        $this->assertContains('Perkenalan pertama', $titles);
    }

    public function test_blog_related_and_popular(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $a = BlogPost::create(['user_id' => $author->id, 'title' => 'Tips Kencan Pertama', 'body' => 'a', 'status' => 'published', 'published_at' => now(), 'view_count' => 50]);
        $b = BlogPost::create(['user_id' => $author->id, 'title' => 'Tips Kencan Kedua', 'body' => 'b', 'status' => 'published', 'published_at' => now(), 'view_count' => 90]);
        BlogPost::create(['user_id' => $other->id, 'title' => 'Artikel Lain', 'body' => 'c', 'status' => 'published', 'published_at' => now(), 'view_count' => 200]);

        $user = User::factory()->create();
        $popular = $this->actingAs($user)->getJson('/api/v1/blog/popular')->assertOk()->json();
        $this->assertEquals('Artikel Lain', $popular[0]['title']);

        $related = $this->actingAs($user)->getJson("/api/v1/blog/{$a->slug}/related")->assertOk()->json();
        $ids = array_column($related, 'id');
        $this->assertContains($b->id, $ids);
        $this->assertNotContains($a->id, $ids);
    }

    public function test_event_upcoming_and_mine(): void
    {
        $user = User::factory()->create();
        $past = Event::create(['host_id' => $user->id, 'title' => 'Kopi Kopi', 'status' => 'published', 'starts_at' => now()->subDay()]);
        $upcoming = Event::create(['host_id' => $user->id, 'title' => 'Ngopi Bareng', 'status' => 'published', 'starts_at' => now()->addDays(2)]);
        $upcoming->members()->create(['user_id' => $user->id, 'status' => 'confirmed']);

        $list = $this->actingAs($user)->getJson('/api/v1/events/upcoming')->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertEquals('Ngopi Bareng', $list[0]['title']);

        $mine = $this->actingAs($user)->getJson('/api/v1/events/mine')->assertOk()->json('data');
        $this->assertCount(1, $mine);
        $this->assertEquals($upcoming->id, $mine[0]['id']);
    }

    public function test_admin_kpi_engagement_and_top_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        \App\Models\Like::create(['liker_id' => $u1->id, 'liked_id' => $u2->id]);
        \App\Models\Like::create(['liker_id' => $u3->id, 'liked_id' => $u2->id]);
        \App\Models\Like::create(['liker_id' => $admin->id, 'liked_id' => $u2->id]);
        \App\Models\Like::create(['liker_id' => $u1->id, 'liked_id' => $admin->id]);

        $conv = \App\Models\Conversation::create(['type' => \App\Enums\ConversationType::Direct, 'created_by' => $u1->id]);
        \App\Models\ConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $u1->id, 'joined_at' => now()]);
        \App\Models\ConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $u2->id, 'joined_at' => now()]);
        \App\Models\Message::create(['conversation_id' => $conv->id, 'sender_id' => $u1->id, 'body' => 'Hai']);

        $kpi = $this->get('/admin/analytics/kpi')->assertOk()->json();
        $this->assertGreaterThanOrEqual(4, $kpi['users_total']);
        $this->assertEquals(4, $kpi['likes_total']);
        $this->assertEquals(1, $kpi['messages_total']);

        $engagement = $this->get('/admin/analytics/engagement?days=7')->assertOk()->json();
        $this->assertGreaterThan(0, count($engagement));
        $this->assertArrayHasKey('likes', $engagement[0]);

        $top = $this->get('/admin/analytics/top-users')->assertOk()->json();
        $this->assertEquals($u2->id, $top['most_liked'][0]['user_id']);
    }
}