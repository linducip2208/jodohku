<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Gift;
use App\Models\Interest;
use App\Models\MembershipPlan;
use App\Models\MessageDeletion;
use App\Models\ProfilePhoto;
use App\Models\User;
use App\Services\AiChatAssistantService;
use App\Services\ChatService;
use App\Services\DiscoveryService;
use App\Services\GiftService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpansionDiscoveryChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_filters_religion_photo_and_keyword(): void
    {
        $me = User::factory()->create();
        $islam = User::factory()->create();
        $islam->profile()->create(['religion' => 'Islam', 'marital_status' => 'single', 'height_cm' => 170]);
        ProfilePhoto::create(['user_id' => $islam->id, 'path' => 'photos/a.jpg', 'status' => 'approved']);

        $kristen = User::factory()->create();
        $kristen->profile()->create(['religion' => 'Kristen']);

        $named = User::factory()->create(['display_name' => 'Zaviera Unik']);
        $named->profile()->create(['religion' => 'Islam', 'marital_status' => 'single', 'height_cm' => 160]);
        ProfilePhoto::create(['user_id' => $named->id, 'path' => 'photos/b.jpg', 'status' => 'approved']);

        $svc = app(DiscoveryService::class);

        $onlyIslam = $svc->discover($me, ['religion' => 'Islam'])->getCollection();
        $this->assertGreaterThan(0, $onlyIslam->count());
        foreach ($onlyIslam as $cand) {
            $this->assertEquals('Islam', $cand->profile->religion);
        }

        $withPhoto = $svc->discover($me, ['has_photo' => 'true'])->getCollection();
        $this->assertTrue($withPhoto->contains('id', $islam->id));

        $byKeyword = $svc->discover($me, ['keyword' => 'Zaviera'])->getCollection();
        $this->assertCount(1, $byKeyword);
        $this->assertEquals($named->id, $byKeyword->first()->id);
    }

    public function test_daily_picks_rotate_without_repeating_same_day(): void
    {
        $me = User::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            User::factory()->create();
        }
        $svc = app(DiscoveryService::class);

        $first = $svc->dailyPicks($me, 3);
        $this->assertCount(3, $first);
        $firstIds = array_column($first, 'user_id');
        $this->assertNotContains($me->id, $firstIds);
        $this->assertCount(3, array_unique($firstIds));

        $second = $svc->dailyPicks($me, 3);
        $secondIds = array_column($second, 'user_id');
        $this->assertCount(3, array_unique($secondIds));
        $this->assertEmpty(array_intersect($firstIds, $secondIds));

        $svc->resetDailyPicks($me);
        $third = $svc->dailyPicks($me, 3);
        $this->assertCount(3, $third);
    }

    public function test_chat_overview_search_stats_and_clear_history(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $chat = app(ChatService::class);

        $conv = $chat->findOrCreateDirect($a, $b);
        $chat->sendMessage($conv, $a, ['body' => 'Halo bro, mau kenalan?']);
        $chat->sendMessage($conv, $b, ['body' => 'Hai! Salam kenal']);
        $chat->sendMessage($conv, $a, ['body' => 'Sama-sama semoga cocok']);

        $overview = $chat->overview($a);
        $this->assertEquals(1, $overview['total_conversations']);
        $this->assertEquals(1, $overview['unread_total']);

        $found = $chat->searchConversations($a, 'kenalan');
        $this->assertCount(1, $found);
        $this->assertEquals($conv->id, $found[0]['conversation_id']);

        $stats = $chat->conversationStats($conv, $a);
        $this->assertEquals(3, $stats['total_messages']);
        $this->assertEquals(2, $stats['my_messages']);
        $this->assertEquals(1, $stats['their_messages']);

        $cleared = $chat->clearHistory($conv, $a);
        $this->assertEquals(3, $cleared);
        $deletions = MessageDeletion::whereIn('message_id', $conv->messages()->pluck('id'))->where('user_id', $a->id)->count();
        $this->assertEquals(3, $deletions);
    }

    public function test_membership_feature_matrix_and_current_features(): void
    {
        $user = User::factory()->create();
        MembershipPlan::create([
            'code' => 'premium_monthly', 'name' => 'Premium', 'price' => 99000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'daily_likes_limit' => 100,
            'monthly_super_likes' => 10, 'monthly_boosts' => 3,
            'has_read_receipts' => true, 'has_incognito' => true, 'is_active' => true, 'sort_order' => 1,
            'features' => ['unlimited_rewind' => true],
        ]);

        $service = app(MembershipService::class);
        $matrix = $service->featureMatrix();
        $this->assertCount(1, $matrix);
        $this->assertEquals('premium_monthly', $matrix->first()['code']);
        $this->assertTrue($matrix->first()['has_read_receipts']);
        $this->assertEquals(['unlimited_rewind' => true], $matrix->first()['features']);

        $free = $service->currentFeatures($user);
        $this->assertEquals(config('jodohku.free_daily_likes', 20), $free['daily_likes_limit']);
        $this->assertFalse($free['has_incognito']);
    }

    public function test_ai_openers_grounded_on_shared_interests(): void
    {
        $me = User::factory()->create();
        $cand = User::factory()->create();
        $interest = Interest::create(['name' => 'Traveling', 'slug' => 'traveling', 'is_active' => true]);
        $me->interests()->attach($interest->id);
        $cand->interests()->attach($interest->id);

        $openers = app(AiChatAssistantService::class)->openers($me, $cand, 3);
        $this->assertCount(3, $openers);
        $this->assertStringContainsStringIgnoringCase('traveling', $openers[0]);
    }

    public function test_gift_received_sent_and_stats(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        Gift::create(['code' => 'rose', 'name' => 'Bunga', 'credit_price' => 0, 'is_active' => true, 'sort_order' => 1]);

        $svc = app(GiftService::class);
        $svc->send($sender, $receiver, 'rose', 1);
        $svc->send($receiver, $sender, 'rose', 1);

        $this->assertEquals(1, $svc->received($receiver)->total());
        $this->assertEquals(1, $svc->received($sender)->total());
        $this->assertEquals(1, $svc->sent($receiver)->total());
        $this->assertEquals(1, $svc->sent($sender)->total());

        $stats = $svc->stats($sender);
        $this->assertEquals(1, $stats['gifts_received']);
        $this->assertEquals(1, $stats['gifts_sent']);
    }
}