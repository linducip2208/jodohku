<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Events\CallInvite;
use App\Events\CallStatusChanged;
use App\Livewire\ChatWindow;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\FraudRiskScore;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\MembershipPlan;
use App\Models\Message;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\AiService;
use App\Services\CallService;
use App\Services\ChatService;
use App\Services\CreditService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ChatExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected function pair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);

        return [$a, $b, $conv];
    }

    public function test_scheduled_messages_flow(): void
    {
        [$a, $b, $conv] = $this->pair();

        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/scheduled-messages", [
            'body' => 'Selamat pagi', 'send_at' => now()->addHour()->toDateTimeString(),
        ])->assertCreated();
        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/scheduled-messages", [
            'body' => 'masa lalu', 'send_at' => now()->subHour()->toDateTimeString(),
        ])->assertStatus(422);
        $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/scheduled-messages")->assertOk()->assertJsonCount(1, 'data');

        // Make it due and dispatch.
        ScheduledMessage::first()->update(['send_at' => now()->subMinute()]);
        $result = app(ChatService::class)->dispatchDue();
        $this->assertEquals(['sent' => 1, 'failed' => 0], $result);
        $this->assertEquals('sent', ScheduledMessage::first()->status->value);
        $this->assertEquals(1, Message::where('conversation_id', $conv->id)->count());

        // Cancel a pending one; double cancel rejected.
        $id = $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/scheduled-messages", [
            'body' => 'nanti', 'send_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertCreated()->json('id');
        $this->actingAs($a)->deleteJson("/api/v1/scheduled-messages/{$id}")->assertOk();
        $this->actingAs($a)->deleteJson("/api/v1/scheduled-messages/{$id}")->assertStatus(422);
    }

    public function test_paid_voice_and_video_calls_with_tokens(): void
    {
        Event::fake([CallInvite::class, CallStatusChanged::class]);
        [$a, $b, $conv] = $this->pair();
        app(CreditService::class)->award($a, 100, 'Topup');

        $this->actingAs($a)->getJson('/api/v1/calls/rates')->assertOk()
            ->assertJsonPath('voice_per_minute', 5)
            ->assertJsonPath('video_per_minute', 10);

        // Broke user cannot invite.
        $poor = User::factory()->create();
        $convPoor = app(ChatService::class)->findOrCreateDirect($poor, $b);
        $this->actingAs($poor)->postJson("/api/v1/conversations/{$convPoor->id}/calls", ['type' => 'voice'])
            ->assertStatus(422);

        $callId = $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/calls", ['type' => 'video'])
            ->assertCreated()->assertJsonPath('status', 'ringing')->json('id');
        Event::assertDispatched(CallInvite::class);
        // Busy conversation rejects second invite.
        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/calls", ['type' => 'voice'])->assertStatus(422);

        // Stranger cannot accept (not a participant).
        $this->actingAs($poor)->postJson("/api/v1/calls/{$callId}/accept")->assertForbidden();
        $this->actingAs($b)->postJson("/api/v1/calls/{$callId}/accept")->assertOk()->assertJsonPath('status', 'ongoing');

        $this->actingAs($a)->postJson("/api/v1/calls/{$callId}/end")->assertOk()
            ->assertJsonPath('status', 'ended');
        $call = Call::find($callId);
        $this->assertEquals(CallStatus::Ended, $call->status);
        $this->assertGreaterThanOrEqual(1, $call->duration_seconds);
        // 1 minute of video = 10 tokens.
        $this->assertEquals(10, $call->credits_charged);
        $this->assertEquals(90, app(CreditService::class)->balance($a));

        // Expired ringing becomes missed.
        $call2 = $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/calls", ['type' => 'voice'])
            ->assertCreated()->json('id');
        Call::where('id', $call2)->update(['created_at' => now()->subMinutes(5)]);
        $this->assertEquals(1, app(CallService::class)->expireRinging());
        $this->assertEquals(CallStatus::Missed, Call::find($call2)->status);
    }

    public function test_chaperone_read_only(): void
    {
        [$x, $y] = [$a1 = User::factory()->create(), User::factory()->create()];
        [$u1, $u2] = UserMatch::canonical($a1->id, $y->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);
        $courtId = $this->actingAs($a1)->postJson('/api/v1/courtships', ['partner_id' => $y->id])->assertCreated()->json('id');

        $wali = User::factory()->create();
        // Chaperone only from taaruf stage.
        $this->actingAs($a1)->postJson("/api/v1/courtships/{$courtId}/chaperone", ['user_id' => $wali->id])->assertStatus(422);
        $this->actingAs($a1)->postJson("/api/v1/courtships/{$courtId}/advance")->assertOk();

        $this->actingAs($a1)->postJson("/api/v1/courtships/{$courtId}/chaperone", ['user_id' => $wali->id])
            ->assertCreated();
        $conv = Conversation::whereHas('members', fn ($q) => $q->where('user_id', $wali->id))->first();
        $this->assertNotNull($conv);
        $this->assertEquals('chaperone', $conv->members()->where('user_id', $wali->id)->first()->role);

        // Chaperone can read but cannot send.
        $this->actingAs($wali)->getJson("/api/v1/conversations/{$conv->id}/messages")->assertOk();
        $this->actingAs($wali)->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'saya wali'])->assertForbidden();

        // Sender quota still counts the peer, not the chaperone.
        $this->assertEquals(0, app(ChatService::class)->sentToPeerCount($a1->id, $y->id));

        $this->actingAs($a1)->deleteJson("/api/v1/courtships/{$courtId}/chaperone", ['user_id' => $wali->id])->assertOk();
        $this->assertFalse($conv->fresh()->involves($wali->id));
    }

    public function test_stickers_themes_polls(): void
    {
        [$a, $b, $conv] = $this->pair();
        $a = tap($a)->update(['is_premium' => true]);
        $a->refresh();

        $this->actingAs($a)->getJson('/api/v1/chat/stickers')->assertOk()->assertJsonCount(12);
        $this->actingAs($a)->getJson('/api/v1/chat/themes')->assertOk()->assertJsonCount(5);

        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => '❤️', 'type' => 'sticker'])->assertCreated();
        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/messages", ['body' => 'bukan stiker', 'type' => 'sticker'])->assertStatus(422);

        $pollId = $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'body' => 'Ketemuan di mana?', 'type' => 'poll', 'metadata' => ['options' => ['Kafe', 'Taman']],
        ])->assertCreated()->json('id');
        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/messages", [
            'body' => 'Invalid', 'type' => 'poll', 'metadata' => ['options' => ['Satu']],
        ])->assertStatus(422);

        $this->actingAs($b)->postJson("/api/v1/messages/{$pollId}/poll/vote", ['option_index' => 0])->assertOk()
            ->assertJsonPath('total_votes', 1)->assertJsonPath('my_vote', 0);
        $this->actingAs($b)->postJson("/api/v1/messages/{$pollId}/poll/vote", ['option_index' => 1])->assertOk()
            ->assertJsonPath('total_votes', 1)->assertJsonPath('my_vote', 1);
        $this->actingAs($b)->getJson("/api/v1/messages/{$pollId}/poll")->assertOk()->assertJsonPath('options.1.votes', 1);
        $this->actingAs($b)->postJson("/api/v1/messages/{$pollId}/poll/vote", ['option_index' => 9])->assertStatus(422);
    }

    public function test_translate_and_catch_up(): void
    {
        [$a, $b, $conv] = $this->pair();
        app(ChatService::class)->sendMessage($conv, $b, ['body' => 'selamat pagi semuanya']);

        $msg = Message::where('conversation_id', $conv->id)->first();
        $mock = $this->mock(AiService::class);
        $mock->shouldReceive('chat')->andReturn(['text' => 'good morning everyone']);

        $this->actingAs($a)->postJson("/api/v1/messages/{$msg->id}/translate", ['target' => 'en'])->assertOk()
            ->assertJsonPath('translated', 'good morning everyone')
            ->assertJsonPath('machine', true);
        $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/catch-up")->assertOk()
            ->assertJsonPath('has_updates', true)
            ->assertJsonPath('count', 1);
    }

    public function test_csv_export_and_safety_hint_and_disappearing(): void
    {
        [$a, $b, $conv] = $this->pair();
        app(ChatService::class)->sendMessage($conv, $a, ['body' => 'halo "dunia"']);

        $csv = $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/export?format=csv")->assertOk();
        $this->assertStringContainsString('id,sent_at,sender_id', $csv->getContent());
        $this->assertStringContainsString('halo ""dunia""', $csv->getContent());

        FraudRiskScore::create(['user_id' => $b->id, 'score' => 80, 'level' => 'high', 'scored_at' => now()]);
        $this->actingAs($a)->getJson("/api/v1/chat/{$conv->id}/safety")->assertOk()
            ->assertJsonPath('risk_level', 'high')
            ->assertJsonPath('warning', true);

        $this->actingAs($a)->patchJson("/api/v1/conversations/{$conv->id}/disappearing", ['seconds' => 3600])->assertOk();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->patchJson("/api/v1/conversations/{$conv->id}/disappearing", ['seconds' => 3600])->assertForbidden();

        Message::where('conversation_id', $conv->id)->update(['created_at' => now()->subHours(2)]);
        $this->assertEquals(1, app(ChatService::class)->pruneDisappearing());
        $this->assertEquals(0, Message::where('conversation_id', $conv->id)->count());
    }

    public function test_gift_from_chat_window_and_read_gate(): void
    {
        $a = User::factory()->premium()->create();
        $b = User::factory()->premium()->create();
        $plan = MembershipPlan::create([
            'code' => 'vip', 'name' => 'VIP', 'price' => 99000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
            'has_read_receipts' => true,
        ]);
        app(SubscriptionService::class)->activate($a, $plan);
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);
        Gift::create(['code' => 'ROSE', 'name' => 'Rose', 'credit_price' => 10, 'is_active' => true]);
        app(CreditService::class)->award($a, 100, 'Topup');

        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('giftCode', 'ROSE')->call('sendGift')->assertHasNoErrors();
        $this->assertEquals(1, GiftTransaction::where('sender_id', $a->id)->count());

        // Premium sender sees read receipts after peer reads.
        app(ChatService::class)->sendMessage($conv, $a, ['body' => 'hai']);
        app(ChatService::class)->markRead($conv, $b);
        $html = Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])->html();
        $this->assertStringContainsString('dibaca', $html);

        // Free sender sees only "terkirim".
        $f1 = User::factory()->create();
        $f2 = User::factory()->create();
        $conv2 = app(ChatService::class)->findOrCreateDirect($f1, $f2);
        app(ChatService::class)->sendMessage($conv2, $f1, ['body' => 'hai free']);
        app(ChatService::class)->markRead($conv2, $f2);
        $html2 = Livewire::actingAs($f1)->test(ChatWindow::class, ['conversationId' => $conv2->id])->html();
        $this->assertStringNotContainsString('dibaca', $html2);
    }
}
