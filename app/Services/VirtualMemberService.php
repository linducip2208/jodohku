<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\AiMode;
use App\Enums\VirtualConversationStatus;
use App\Models\AiPersonality;
use App\Models\ChatTrigger;
use App\Models\User;
use App\Models\VirtualConversation;
use App\Models\VirtualProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VirtualMemberService
{
    public function __construct(protected AuditService $audit) {}

    public function createVirtual(array $attrs): User
    {
        return DB::transaction(function () use ($attrs) {
            $user = User::create([
                'name' => $attrs['name'],
                'email' => $attrs['email'] ?? 'virtual_'.Str::random(8).'@virtual.jodohku',
                'password' => Hash::make(Str::random(32)),
                'account_type' => $attrs['account_type'] ?? AccountType::Virtual->value,
                'role' => 'member',
                'status' => 'active',
                'display_name' => $attrs['display_name'] ?? $attrs['name'],
                'gender' => $attrs['gender'] ?? null,
                'date_of_birth' => $attrs['date_of_birth'] ?? now()->subYears(25)->toDateString(),
                'city' => $attrs['city'] ?? null,
                'is_verified' => false,
            ]);
            VirtualProfile::create([
                'user_id' => $user->id,
                'ai_personality_id' => $attrs['ai_personality_id'] ?? AiPersonality::active()->first()?->id,
                'mode' => $attrs['mode'] ?? AiMode::Template->value,
                'persona_prompt' => $attrs['persona_prompt'] ?? null,
                'greeting_message' => $attrs['greeting_message'] ?? ('Halo! Aku '.$user->displayName().' 👋 (profil virtual Jodohku)'),
                'reply_templates' => $attrs['reply_templates'] ?? ['Hai! Senang kenalan denganmu 😊', 'Wah menarik! Ceritakan lebih banyak dong.'],
                'is_active' => true,
            ]);
            $this->audit->log('virtual.created', null, $user, [], ['transparent' => true]);

            return $user;
        });
    }

    /** Transparent label required on every synthetic profile. */
    public function label(User $user): string
    {
        if ($user->account_type === AccountType::Ai) {
            return 'AI Persona — dikelola otomatis oleh Jodohku';
        }
        if ($user->account_type === AccountType::Virtual) {
            return 'Profil Virtual — dikelola Jodohku/operator';
        }

        return '';
    }

    /**
     * Trigger engine: cooldown + active-hours + daily-cap checks, modes template/hybrid/ai.
     * Returns sent message body or null when suppressed.
     */
    public function handleEvent(string $eventName, User $realUser, array $context = []): ?string
    {
        if (! config('jodohku.features.virtual', true)) {
            return null;
        }
        $triggers = ChatTrigger::active()->forEvent($eventName)->with('actions')->get();
        if ($triggers->isEmpty()) {
            return null;
        }

        // Daily cap per real user
        $todayCount = VirtualConversation::where('real_user_id', $realUser->id)->whereDate('created_at', today())->count();
        if ($todayCount >= (int) config('jodohku.virtual.max_daily_messages', 5)) {
            return null;
        }

        // Active hours (configurable, default 08:00–22:00 local)
        $hour = (int) now()->format('H');
        if ($hour < (int) config('jodohku.virtual.active_from_hour', 8) || $hour > (int) config('jodohku.virtual.active_until_hour', 22)) {
            return null;
        }

        foreach ($triggers as $trigger) {
            // Cooldown per user+trigger
            $cooldown = (int) ($trigger->cooldown_minutes ?? 0);
            if ($cooldown > 0) {
                $recent = VirtualConversation::where('real_user_id', $realUser->id)
                    ->where('created_at', '>', now()->subMinutes($cooldown))->exists();
                if ($recent) {
                    continue;
                }
            }

            $profile = VirtualProfile::where('is_active', true)->inRandomOrder()->first();
            if (! $profile) {
                return null;
            }
            $virtualUser = $profile->user;
            if (! $virtualUser) {
                continue;
            }

            /** @var ChatService $chat */
            $chat = app(ChatService::class);
            try {
                $conv = $chat->findOrCreateDirect($virtualUser, $realUser);
            } catch (\Throwable) {
                continue;
            }

            $mode = $profile->mode instanceof AiMode ? $profile->mode->value : (string) $profile->mode;
            $body = $this->composeReply($profile, $mode, $realUser, $eventName, $context);
            if (! $body) {
                continue;
            }

            try {
                $chat->sendMessage($conv, $virtualUser, [
                    'body' => $body.' — '.$this->label($virtualUser),
                    'metadata' => ['virtual' => true, 'trigger' => $trigger->name, 'mode' => $mode],
                ], 'virtual-'.$trigger->id.'-'.$realUser->id.'-'.now()->format('YmdHi'));
            } catch (\Throwable) {
                continue;
            }

            VirtualConversation::firstOrCreate(
                ['conversation_id' => $conv->id, 'virtual_profile_id' => $profile->id],
                ['real_user_id' => $realUser->id, 'mode' => $mode, 'status' => VirtualConversationStatus::Active]
            );

            return $body;
        }

        return null;
    }

    protected function composeReply(VirtualProfile $profile, string $mode, User $realUser, string $event, array $ctx): ?string
    {
        if ($mode === AiMode::Template->value || $mode === 'template') {
            $templates = $profile->reply_templates ?? [];
            if (empty($templates)) {
                return $profile->greeting_message ?? 'Halo! Senang kenalan denganmu 😊';
            }

            return $templates[array_rand($templates)];
        }
        if ($mode === AiMode::Ai->value || $mode === 'ai') {
            try {
                /** @var AiService $ai */
                $ai = app(AiService::class);
                $res = $ai->chat(
                    'Kamu persona ramah Jodohku bernama '.$profile->user?->displayName().'. Event: '.$event.'. Sapa user '.$realUser->displayName().' singkat (max 40 kata), Bahasa Indonesia.',
                    ['max_tokens' => 120], null, 'virtual_reply'
                );

                return trim((string) $res['text']);
            } catch (\Throwable) {
                $templates = $profile->reply_templates ?? ['Halo! Senang kenalan denganmu 😊'];

                return $templates[array_rand($templates)];
            }
        }
        // hybrid: template mostly, AI occasionally
        if (random_int(1, 100) <= 25) {
            return $this->composeReply($profile, AiMode::Ai->value, $realUser, $event, $ctx);
        }

        return $this->composeReply($profile, AiMode::Template->value, $realUser, $event, $ctx);
    }
}
