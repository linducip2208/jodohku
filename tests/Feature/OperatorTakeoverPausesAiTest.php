<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\AiMode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VirtualConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VirtualConversation;
use App\Models\VirtualProfile;
use App\Services\OperatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperatorTakeoverPausesAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_takeover_sets_ai_paused_at_and_resume_clears(): void
    {
        $real = User::factory()->create();
        $virtualUser = User::factory()->create(['account_type' => AccountType::Virtual]);
        $operator = User::factory()->create(['role' => UserRole::Operator, 'account_type' => AccountType::Operator]);

        $profile = VirtualProfile::create([
            'user_id' => $virtualUser->id,
            'mode' => AiMode::Template,
            'greeting_message' => 'Halo!',
            'reply_templates' => ['Hai!'],
            'is_active' => true,
        ]);

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $real->id]);
        $conversation->members()->create(['user_id' => $real->id]);
        $conversation->members()->create(['user_id' => $virtualUser->id]);

        $vc = VirtualConversation::create([
            'conversation_id' => $conversation->id,
            'virtual_profile_id' => $profile->id,
            'real_user_id' => $real->id,
            'mode' => AiMode::Template,
            'status' => VirtualConversationStatus::Active,
        ]);
        $this->assertNull($vc->ai_paused_at);

        $svc = app(OperatorService::class);
        $svc->takeover($vc->fresh(), $operator);

        $taken = $vc->fresh();
        $this->assertNotNull($taken->ai_paused_at);
        $this->assertEquals(VirtualConversationStatus::Transferred, $taken->status);
        $this->assertEquals($operator->id, $taken->operator_id);

        $svc->resume($taken, $operator);
        $resumed = $vc->fresh();
        $this->assertNull($resumed->ai_paused_at);
        $this->assertEquals(VirtualConversationStatus::Active, $resumed->status);
    }
}
