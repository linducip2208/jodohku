<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function log(string $action, ?User $actor = null, ?Model $auditable = null, array $old = [], array $new = []): AuditLog
    {
        // Never log secrets: strip sensitive keys
        $scrub = fn (array $arr) => collect($arr)->except([
            'password', 'remember_token', 'api_key', 'secret', 'private_key', 'server_key',
            'otp', 'token', 'credit_card', 'cvv',
        ])->all();

        return AuditLog::record($action, $actor, $auditable, $scrub($old), $scrub($new));
    }
}
