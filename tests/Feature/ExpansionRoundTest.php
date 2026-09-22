<?php

namespace Tests\Feature;

use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpansionRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_gift_leaderboard_and_trending(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $gift = Gift::create(['code' => 'ROSE', 'name' => 'Rose', 'credit_price' => 10, 'is_active' => true]);
        GiftTransaction::create(['gift_id' => $gift->id, 'sender_id' => $a->id, 'receiver_id' => $b->id, 'quantity' => 2, 'credits_spent' => 20]);
        GiftTransaction::create(['gift_id' => $gift->id, 'sender_id' => $a->id, 'receiver_id' => $c->id, 'quantity' => 1, 'credits_spent' => 10]);
        GiftTransaction::create(['gift_id' => $gift->id, 'sender_id' => $b->id, 'receiver_id' => $a->id, 'quantity' => 1, 'credits_spent' => 10]);

        $this->actingAs($a)->getJson('/api/v1/gifts/leaderboard')->assertOk()
            ->assertJsonPath('top_senders.0.user_id', $a->id)
            ->assertJsonPath('top_senders.0.gifts', 2);
        $this->actingAs($a)->getJson('/api/v1/gifts/leaderboard?period=bogus')->assertStatus(422);
        $this->actingAs($a)->getJson('/api/v1/gifts/trending')->assertOk()
            ->assertJsonPath('0.transactions', 3)
            ->assertJsonPath('0.gift.code', 'ROSE');
    }

    public function test_forum_owner_edit_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $forum = Forum::create(['name' => 'Taaruf', 'slug' => 'taaruf', 'is_active' => true]);
        $thread = ForumThread::create(['forum_id' => $forum->id, 'user_id' => $owner->id, 'title' => 'Assalamualaikum', 'body' => 'Kenalan yuk']);
        $reply = ForumReply::create(['thread_id' => $thread->id, 'user_id' => $owner->id, 'body' => 'Balasan saya']);

        // Non-owner forbidden.
        $this->actingAs($other)->putJson("/api/v1/forum-threads/{$thread->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/v1/forum-replies/{$reply->id}")->assertForbidden();

        $this->actingAs($owner)->putJson("/api/v1/forum-threads/{$thread->id}", ['title' => 'Assalamualaikum wr wb'])->assertOk()
            ->assertJsonPath('title', 'Assalamualaikum wr wb');
        $this->actingAs($owner)->putJson("/api/v1/forum-replies/{$reply->id}", ['body' => 'Balasan diedit'])->assertOk();

        $this->actingAs($owner)->deleteJson("/api/v1/forum-replies/{$reply->id}")->assertOk();
        $this->assertSoftDeleted('forum_replies', ['id' => $reply->id]);
        $this->assertEquals(0, $thread->refresh()->reply_count);

        $this->actingAs($owner)->deleteJson("/api/v1/forum-threads/{$thread->id}")->assertOk();
        $this->assertSoftDeleted('forum_threads', ['id' => $thread->id]);
    }

    public function test_score_cache_route(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->getJson("/api/v1/matches/{$b->id}/score-cache")->assertOk()
            ->assertJsonStructure(['mutual', 'breakdown']);
    }
}
