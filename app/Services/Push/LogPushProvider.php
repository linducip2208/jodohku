<?php

namespace App\Services\Push;

use App\Models\PushToken;
use Illuminate\Support\Facades\Log;

class LogPushProvider implements PushProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function send(PushToken $token, string $title, string $body, array $data = []): bool
    {
        Log::info('push.send', ['user' => $token->user_id, 'platform' => $token->platform, 'title' => $title, 'data' => $data]);

        return true;
    }
}
