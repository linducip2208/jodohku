<?php

namespace App\Services\Push;

use App\Models\PushToken;

interface PushProvider
{
    public function name(): string;

    /**
     * @throws TokenInvalidException when the token is dead and must be disabled.
     */
    public function send(PushToken $token, string $title, string $body, array $data = []): bool;
}
