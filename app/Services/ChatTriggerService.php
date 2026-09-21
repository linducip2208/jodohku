<?php

namespace App\Services;

use App\Jobs\ProcessVirtualChatTrigger;
use App\Models\ChatTrigger;
use App\Models\User;

class ChatTriggerService
{
    /** Evaluate DB-defined triggers for an event and queue virtual replies. */
    public function fire(string $eventName, User $user, array $context = []): int
    {
        $triggers = ChatTrigger::active()->forEvent($eventName)->get();
        if ($triggers->isEmpty()) {
            return 0;
        }
        $n = 0;
        foreach ($triggers as $trigger) {
            if (! $this->conditionsPass($trigger->conditions ?? [], $user, $context)) {
                continue;
            }
            ProcessVirtualChatTrigger::dispatch($eventName, $user->id, $context);
            $n++;
        }

        return $n;
    }

    protected function conditionsPass(array $conditions, User $user, array $context): bool
    {
        foreach ($conditions as $key => $expected) {
            $actual = match ($key) {
                'city' => $user->city,
                'is_premium' => $user->isPremium(),
                'is_verified' => (bool) $user->is_verified,
                'account_type' => $user->account_type?->value ?? (string) $user->account_type,
                default => $context[$key] ?? null,
            };
            if (is_array($expected)) {
                if (! in_array($actual, $expected, true)) {
                    return false;
                }
            } elseif ($actual != $expected) {
                return false;
            }
        }

        return true;
    }
}
