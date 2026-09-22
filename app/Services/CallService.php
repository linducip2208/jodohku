<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Events\CallInvite;
use App\Events\CallStatusChanged;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CallService
{
    public function __construct(protected CreditService $credits, protected AuditService $audit) {}

    public function ratePerMinute(string $type): int
    {
        return $type === 'video'
            ? max(1, (int) Setting::get('video_per_minute', 10, 'calls'))
            : max(1, (int) Setting::get('voice_per_minute', 5, 'calls'));
    }

    public function inviteTtlSeconds(): int
    {
        return max(15, (int) Setting::get('invite_ttl_seconds', 60, 'calls'));
    }

    public function invite(User $caller, Conversation $conversation, string $type = 'voice'): Call
    {
        if (! in_array($type, ['voice', 'video'], true)) {
            throw new \InvalidArgumentException('Invalid call type.');
        }
        if (! $conversation->involves((int) $caller->id)) {
            throw new \RuntimeException('Not a conversation member.');
        }
        if ($conversation->is_blocked) {
            throw new \RuntimeException('Conversation is blocked.');
        }
        $peer = $conversation->otherMember((int) $caller->id);
        if (! $peer || ! $peer->user) {
            throw new \RuntimeException('No peer to call.');
        }
        $busy = Call::where('conversation_id', $conversation->id)
            ->whereIn('status', [CallStatus::Ringing->value, CallStatus::Ongoing->value])->exists();
        if ($busy) {
            throw new \RuntimeException('A call is already active in this conversation.');
        }
        // Both voice and video are paid with tokens: caller must afford the first minute.
        $rate = $this->ratePerMinute($type);
        if ($this->credits->balance($caller) < $rate) {
            throw new \RuntimeException("Insufficient tokens: {$type} call costs {$rate} tokens/minute.");
        }

        return DB::transaction(function () use ($caller, $conversation, $peer, $type) {
            $call = Call::create([
                'conversation_id' => $conversation->id,
                'caller_id' => $caller->id,
                'receiver_id' => $peer->user->id,
                'type' => $type,
                'status' => CallStatus::Ringing,
            ]);
            $this->audit->log('call.invited', $caller, $call, [], ['type' => $type]);
            event(new CallInvite($call->fresh()));

            return $call->fresh();
        });
    }

    public function accept(Call $call, User $user): Call
    {
        $this->ensureReceiver($call, $user);
        $this->ensureRinging($call);

        return DB::transaction(function () use ($call, $user) {
            $call->update(['status' => CallStatus::Ongoing, 'started_at' => now()]);
            $this->audit->log('call.accepted', $user, $call);
            event(new CallStatusChanged($call->fresh(), 'accepted'));

            return $call->fresh();
        });
    }

    public function reject(Call $call, User $user): Call
    {
        $this->ensureReceiver($call, $user);
        $this->ensureRinging($call);

        return DB::transaction(function () use ($call, $user) {
            $call->update(['status' => CallStatus::Rejected, 'ended_at' => now()]);
            $this->audit->log('call.rejected', $user, $call);
            event(new CallStatusChanged($call->fresh(), 'rejected'));

            return $call->fresh();
        });
    }

    public function cancel(Call $call, User $user): Call
    {
        if ((int) $call->caller_id !== (int) $user->id) {
            throw new \RuntimeException('Only caller can cancel.');
        }
        $this->ensureRinging($call);
        $call->update(['status' => CallStatus::Cancelled, 'ended_at' => now()]);
        $this->audit->log('call.cancelled', $user, $call);
        event(new CallStatusChanged($call->fresh(), 'cancelled'));

        return $call->fresh();
    }

    /** End an ongoing call and charge tokens for the used minutes. */
    public function end(Call $call, User $user): Call
    {
        if ((int) $call->caller_id !== (int) $user->id && (int) $call->receiver_id !== (int) $user->id) {
            throw new \RuntimeException('Not a call participant.');
        }
        if ($call->status !== CallStatus::Ongoing) {
            throw new \RuntimeException('Call is not ongoing.');
        }

        return DB::transaction(function () use ($call, $user) {
            $seconds = max(1, $call->started_at ? now()->diffInSeconds($call->started_at) : 1);
            $minutes = (int) ceil($seconds / 60);
            $cost = $minutes * $this->ratePerMinute($call->type);
            $caller = $call->caller;
            $charged = 0;
            if ($caller && $cost > 0) {
                $balance = $this->credits->balance($caller);
                $charged = min($cost, $balance);
                if ($charged > 0) {
                    $this->credits->spend($caller, $charged, "Call #{$call->id} ({$call->type}, {$minutes} min)");
                }
                if ($charged < $cost) {
                    $this->audit->log('call.charge_shortfall', $user, $call, [], ['cost' => $cost, 'charged' => $charged]);
                }
            }
            $call->update([
                'status' => CallStatus::Ended, 'ended_at' => now(),
                'duration_seconds' => $seconds, 'credits_charged' => $charged,
            ]);
            $this->audit->log('call.ended', $user, $call, [], ['duration' => $seconds, 'charged' => $charged]);
            event(new CallStatusChanged($call->fresh(), 'ended'));

            return $call->fresh();
        });
    }

    /** Mark stale ringing calls as missed. */
    public function expireRinging(int $batch = 100): int
    {
        $ids = Call::where('status', CallStatus::Ringing->value)
            ->where('created_at', '<=', now()->subSeconds($this->inviteTtlSeconds()))
            ->limit($batch)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        Call::whereIn('id', $ids)->update(['status' => CallStatus::Missed->value, 'ended_at' => now()]);
        foreach (Call::whereIn('id', $ids)->get() as $call) {
            event(new CallStatusChanged($call, 'missed'));
        }

        return $ids->count();
    }

    public function history(Conversation $conversation, User $user, int $perPage = 20)
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a conversation member.');
        }

        return Call::where('conversation_id', $conversation->id)->with(['caller', 'receiver'])->latest('id')->paginate($perPage);
    }

    protected function ensureReceiver(Call $call, User $user): void
    {
        if ((int) $call->receiver_id !== (int) $user->id) {
            throw new \RuntimeException('Only the receiver can respond.');
        }
    }

    protected function ensureRinging(Call $call): void
    {
        if ($call->status !== CallStatus::Ringing) {
            throw new \RuntimeException('Call is no longer ringing.');
        }
        if ($call->created_at && $call->created_at->lt(now()->subSeconds($this->inviteTtlSeconds()))) {
            $call->update(['status' => CallStatus::Missed, 'ended_at' => now()]);
            throw new \RuntimeException('Call expired (missed).');
        }
    }
}
