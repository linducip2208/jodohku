<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\AiMode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiPersonality;
use App\Models\ChatTrigger;
use App\Models\Message;
use App\Models\User;
use App\Models\VirtualConversation;
use App\Models\VirtualProfile;
use App\Services\VirtualMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VirtualTriggerCreatesAutoChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registered_trigger_creates_auto_chat_within_caps(): void
    {
        // Active hours 08:00–22:00 guard: freeze at noon.
        $this->travelTo(now()->setTime(12, 0));

        $real = User::factory()->create(['city' => 'Jakarta']);

        $personality = AiPersonality::firstOrCreate(['code' => 'ceria'], [
            'name' => 'Ceria', 'system_prompt' => 'Ramah.', 'tone' => 'friendly', 'language' => 'id', 'is_active' => true,
        ]);

        $virtualUser = User::create([
            'name' => 'Sinta Virtual',
            'email' => 'sinta.virtual+test@example.test',
            'password' => Hash::make('secret'),
            'account_type' => AccountType::Virtual,
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'display_name' => 'Sinta',
            'gender' => 'female',
            'date_of_birth' => '1996-06-15',
            'city' => 'Jakarta',
            'country' => 'Indonesia',
        ]);
        VirtualProfile::create([
            'user_id' => $virtualUser->id,
            'ai_personality_id' => $personality->id,
            'mode' => AiMode::Template,
            'greeting_message' => 'Halo!',
            'reply_templates' => ['Hai! Senang kenalan denganmu 😊'],
            'is_active' => true,
        ]);

        ChatTrigger::create([
            'name' => 'Sapa pendaftar baru (test)',
            'event' => 'user.registered',
            'conditions' => [],
            'priority' => 100,
            'cooldown_minutes' => 0,
            'is_active' => true,
        ]);

        $body = app(VirtualMemberService::class)->handleEvent('user.registered', $real);

        $this->assertNotNull($body);
        $this->assertEquals(1, VirtualConversation::where('real_user_id', $real->id)->count());

        $vc = VirtualConversation::where('real_user_id', $real->id)->first();
        $message = Message::where('conversation_id', $vc->conversation_id)->latest('id')->first();
        $this->assertNotNull($message);
        // Transparent label appended to every synthetic message.
        $this->assertStringContainsString('virtual', strtolower($message->body));
    }
}
