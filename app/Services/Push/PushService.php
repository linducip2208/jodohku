<?php

namespace App\Services\Push;

use App\Models\PushToken;
use App\Models\User;

/**
 * Push fan-out behind a provider abstraction. Default driver `log`
 * (auditable no-op); `fcm` when PUSH_FCM_SERVER_KEY is set. Dead tokens
 * are disabled automatically; per-type user preferences are enforced by
 * NotificationService before fan-out is reached.
 */
class PushService
{
    public function provider(): PushProvider
    {
        $driver = strtolower((string) config('push.default', 'log'));

        return match ($driver) {
            'fcm' => new FcmPushProvider,
            default => new LogPushProvider,
        };
    }

    /** @return array{sent:int, disabled:int} */
    public function fanout(User $user, string $title, string $body, array $data = []): array
    {
        $sent = 0;
        $disabled = 0;
        $tokens = PushToken::where('user_id', $user->id)->live()->get();
        if ($tokens->isEmpty()) {
            return compact('sent', 'disabled');
        }
        $provider = $this->provider();
        foreach ($tokens as $token) {
            try {
                if ($provider->send($token, $title, $body, $data)) {
                    $sent++;
                }
            } catch (TokenInvalidException) {
                $token->update(['disabled_at' => now()]);
                $disabled++;
            } catch (\Throwable) {
            }
        }

        return compact('sent', 'disabled');
    }

    public function deepLink(string $type, array $data): ?string
    {
        return match (true) {
            isset($data['conversation_id']) => '/chat/'.$data['conversation_id'],
            isset($data['match_id']) => '/matches',
            isset($data['post_id']) => '/komunitas',
            isset($data['story_id']) => '/stories/'.$data['story_id'],
            isset($data['follower_id']) => '/profile/'.$data['follower_id'],
            isset($data['payment_id']) => '/payments',
            default => null,
        };
    }
}
