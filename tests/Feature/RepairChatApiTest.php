<?php

namespace Tests\Feature;

use App\Enums\ChatRequestStatus;
use App\Models\Block;
use App\Models\ChatRequest;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function pair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);

        return [$a, $b, $conv];
    }

    public function test_labels_crud_via_api(): void
    {
        [$a, $b, $conv] = $this->pair();

        $this->actingAs($a)->getJson("/api/v1/chat/{$conv->id}/labels")->assertOk()->assertJsonCount(0);
        $this->actingAs($a)->postJson("/api/v1/chat/{$conv->id}/labels", ['label' => 'Penting', 'color' => '#ff0000'])
            ->assertCreated()->assertJsonPath('label', 'Penting');
        // Same label twice dedups (no duplicate row).
        $this->actingAs($a)->postJson("/api/v1/chat/{$conv->id}/labels", ['label' => 'Penting'])
            ->assertCreated();
        $this->assertEquals(1, $conv->labels()->where('user_id', $a->id)->count());
        // Nickname setting must NOT be clobbered by addLabel.
        $this->assertNull($conv->settings()->where('user_id', $a->id)->first()?->nickname);

        $labelId = $conv->labels()->where('user_id', $a->id)->first()->id;
        // Missing label id rejected (was silent 404 via request input).
        $this->actingAs($a)->deleteJson("/api/v1/chat/{$conv->id}/labels/0")->assertStatus(422);
        $this->actingAs($a)->deleteJson("/api/v1/chat/{$conv->id}/labels/{$labelId}")->assertOk();
        $this->assertEquals(0, $conv->labels()->where('user_id', $a->id)->count());
        // Outsider cannot manage labels.
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson("/api/v1/chat/{$conv->id}/labels")->assertForbidden();
    }

    public function test_mark_all_read_route_works(): void
    {
        [$a, $b, $conv] = $this->pair();
        app(ChatService::class)->sendMessage($conv, $b, ['body' => 'halo apa kabar']);

        $this->actingAs($a)->postJson("/api/v1/chat/{$conv->id}/mark-all-read")
            ->assertOk()->assertJsonPath('marked', 1);
    }

    public function test_react_unreact_via_api(): void
    {
        [$a, $b, $conv] = $this->pair();
        $msg = app(ChatService::class)->sendMessage($conv, $a, ['body' => 'hai']);

        $this->actingAs($b)->postJson("/api/v1/messages/{$msg->id}/reactions", ['emoji' => '❤️'])
            ->assertCreated();
        // Duplicate react is idempotent.
        $this->actingAs($b)->postJson("/api/v1/messages/{$msg->id}/reactions", ['emoji' => '❤️'])->assertCreated();
        $this->assertEquals(1, $msg->reactions()->count());

        $this->actingAs($b)->getJson("/api/v1/messages/{$msg->id}/reactions")
            ->assertOk()->assertJsonPath('0.count', 1);
        $this->actingAs($b)->deleteJson("/api/v1/messages/{$msg->id}/reactions", ['emoji' => '❤️'])->assertOk();
        $this->assertEquals(0, $msg->reactions()->count());
    }

    public function test_typing_setting_search_export_via_api(): void
    {
        [$a, $b, $conv] = $this->pair();
        app(ChatService::class)->sendMessage($conv, $b, ['body' => 'ketemu di kafe yuk']);

        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/typing", ['is_typing' => true])->assertOk();
        $this->actingAs($a)->patchJson("/api/v1/conversations/{$conv->id}/settings", ['key' => 'is_muted', 'value' => true])
            ->assertOk()->assertJsonPath('is_muted', true);
        $this->actingAs($a)->patchJson("/api/v1/conversations/{$conv->id}/settings", ['key' => 'nope', 'value' => 1])
            ->assertStatus(422);

        $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/search?q=kafe")
            ->assertOk()->assertJsonCount(1);
        $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/export")
            ->assertOk()->assertJsonPath('total_messages', 1);

        // Non-member gets 403 everywhere.
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson("/api/v1/conversations/{$conv->id}/search?q=kafe")->assertForbidden();
        $this->actingAs($outsider)->getJson("/api/v1/conversations/{$conv->id}/export")->assertForbidden();
    }

    public function test_forward_carries_attachments(): void
    {
        Storage::fake('public');
        [$a, $b, $conv] = $this->pair();
        $c = User::factory()->create();
        $conv2 = app(ChatService::class)->findOrCreateDirect($a, $c);

        $msg = app(ChatService::class)->sendAttachment($conv, $a, UploadedFile::fake()->image('foto.jpg'), 'lihat ini');
        $this->assertEquals(1, $msg->attachments()->count());

        $res = $this->actingAs($a)->postJson("/api/v1/messages/{$msg->id}/forward", ['conversation_id' => $conv2->id])
            ->assertCreated();
        $forwardedId = $res->json('id');
        $this->assertEquals(1, Message::find($forwardedId)->attachments()->count());
    }

    public function test_unmatch_via_api(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);

        $this->actingAs($a)->deleteJson("/api/v1/matches/{$b->id}")->assertOk();
        $this->assertFalse(UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->first()->is_active);
    }

    public function test_requests_filter_lists_peer_conversations(): void
    {
        [$a, $b, $conv] = $this->pair();
        $stranger = User::factory()->create();
        $otherConv = app(ChatService::class)->findOrCreateDirect($a, $stranger);
        ChatRequest::create(['sender_id' => $b->id, 'receiver_id' => $a->id, 'status' => ChatRequestStatus::Pending]);

        $this->actingAs($a)->get('/chat/conversations?filter=requests')->assertOk()
            ->assertViewHas('conversations', function ($paginator) use ($conv, $otherConv) {
                $ids = $paginator->pluck('id')->all();

                return in_array($conv->id, $ids) && ! in_array($otherConv->id, $ids);
            });
    }

    public function test_chat_request_state_machine(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        // Blocked users cannot request.
        Block::create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);
        $this->actingAs($a)->postJson("/api/v1/chat-requests/{$b->id}")->assertForbidden();
        Block::where('blocker_id', $a->id)->delete();

        $this->actingAs($a)->postJson("/api/v1/chat-requests/{$b->id}", ['message' => 'hai'])->assertCreated();
        // Reverse duplicate returns the existing pending request (no duplicate row).
        $this->actingAs($b)->postJson("/api/v1/chat-requests/{$a->id}")->assertOk();
        $this->assertEquals(1, ChatRequest::where('status', 'pending')->count());

        $req = ChatRequest::first();
        $this->actingAs($b)->postJson("/api/v1/chat-requests/{$req->id}/action", ['action' => 'accept'])
            ->assertOk()->assertJsonStructure(['conversation_id']);
        // Second transition on terminal state is rejected.
        $this->actingAs($b)->postJson("/api/v1/chat-requests/{$req->id}/action", ['action' => 'decline'])
            ->assertStatus(422);
    }

    public function test_export_excludes_cleared_history(): void
    {
        [$a, $b, $conv] = $this->pair();
        app(ChatService::class)->sendMessage($conv, $b, ['body' => 'rahasia']);
        $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/export")
            ->assertOk()->assertJsonPath('total_messages', 1);

        $this->actingAs($a)->postJson("/api/v1/chat/{$conv->id}/clear-history")->assertOk();
        $this->actingAs($a)->getJson("/api/v1/conversations/{$conv->id}/export")
            ->assertOk()->assertJsonPath('total_messages', 0);
    }
}
