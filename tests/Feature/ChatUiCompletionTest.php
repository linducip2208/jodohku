<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\UserRole;
use App\Livewire\ChatWindow;
use App\Models\Call;
use App\Models\Message;
use App\Models\PollVote;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\AiService;
use App\Services\ChatService;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChatUiCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function pair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);

        return [$a, $b, $conv];
    }

    public function test_schedule_and_cancel_via_livewire(): void
    {
        [$a, $b, $conv] = $this->pair();

        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('scheduleBody', 'Pengingat manis')->set('scheduleAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('schedule')->assertHasNoErrors();
        $this->assertEquals(1, ScheduledMessage::where('sender_id', $a->id)->count());

        $id = ScheduledMessage::first()->id;
        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->call('cancelScheduled', $id)->assertHasNoErrors();
        $this->assertEquals('cancelled', ScheduledMessage::find($id)->status->value);
    }

    public function test_sticker_and_poll_via_livewire(): void
    {
        [$a, $b, $conv] = $this->pair();
        $a->update(['is_premium' => true]);

        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('sticker', '❤️')->call('sendSticker')->assertHasNoErrors();
        $this->assertEquals('sticker', Message::latest('id')->first()->type);

        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('sticker', 'bukan-stiker')->call('sendSticker')->assertHasErrors('sticker');

        $comp = Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('pollQuestion', 'Mau ke mana?')->set('pollOptions', "Kafe\nTaman")->call('sendPoll')->assertHasNoErrors();
        $pollId = Message::latest('id')->first()->id;
        $this->assertEquals('poll', Message::find($pollId)->type);

        Livewire::actingAs($b)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->call('votePoll', $pollId, 1)->assertHasNoErrors();
        $this->assertEquals(1, PollVote::where('message_id', $pollId)->count());

        // Poll form validation: single option rejected.
        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('pollQuestion', 'Q?')->set('pollOptions', 'Satu')->call('sendPoll')->assertHasErrors('pollQuestion');
        unset($comp);
    }

    public function test_call_invite_and_end_via_livewire(): void
    {
        [$a, $b, $conv] = $this->pair();
        app(CreditService::class)->award($a, 100, 'Topup');

        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->call('inviteCall', 'voice')->assertHasNoErrors();
        $call = Call::latest('id')->first();
        $this->assertEquals(CallStatus::Ringing, $call->status);

        // Receiver accepts then ends; caller charged 1 minute of voice.
        Livewire::actingAs($b)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->call('answerCall', $call->id, 'accept')->assertHasNoErrors();
        Livewire::actingAs($b)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->call('answerCall', $call->id, 'end')->assertHasNoErrors();
        $this->assertEquals(CallStatus::Ended, $call->fresh()->status);
        $this->assertEquals(5, $call->fresh()->credits_charged);
    }

    public function test_disappearing_and_translate_via_livewire(): void
    {
        [$a, $b, $conv] = $this->pair();

        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('disappearing', '60')->call('setDisappearing')->assertHasErrors('disappearing');
        Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->set('disappearing', '86400')->call('setDisappearing')->assertHasNoErrors();
        $this->assertEquals(86400, $conv->fresh()->disappears_in_seconds);

        $msg = app(ChatService::class)->sendMessage($conv, $b, ['body' => 'selamat pagi']);
        $mock = $this->mock(AiService::class);
        $mock->shouldReceive('chat')->once()->andReturn(['text' => 'good morning']);
        $html = Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])
            ->call('translate', $msg->id)->assertHasNoErrors()->html();
        $this->assertStringContainsString('good morning', $html);
    }

    public function test_admin_biro_pages(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $mod = User::factory()->create(['role' => UserRole::Moderator]);

        foreach (['courtships', 'counselors', 'consultations', 'stories'] as $page) {
            $this->actingAs($admin)->get("/admin/biro-jodoh/{$page}")->assertOk();
            $this->actingAs($mod)->get("/admin/biro-jodoh/{$page}")->assertOk();
        }
        $this->actingAs(User::factory()->create())->get('/admin/biro-jodoh/stories')->assertForbidden();

        $staff = User::factory()->create();
        $this->actingAs($admin)->post('/admin/biro-jodoh/counselors', [
            'user_id' => $staff->id, 'specialty' => 'Pranikah', 'bio' => 'Berpengalaman.',
        ])->assertRedirect();
        $this->assertDatabaseHas('counselors', ['user_id' => $staff->id]);
    }
}
