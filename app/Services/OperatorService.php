<?php

namespace App\Services;

use App\Enums\VirtualConversationStatus;
use App\Models\Conversation;
use App\Models\OperatorAssignment;
use App\Models\User;
use App\Models\VirtualConversation;
use Illuminate\Support\Facades\DB;

class OperatorService
{
    public function __construct(protected AuditService $audit) {}

    public function takeover(VirtualConversation $vc, User $operator): OperatorAssignment
    {
        return DB::transaction(function () use ($vc, $operator) {
            $vc->update(['operator_id' => $operator->id, 'status' => VirtualConversationStatus::Transferred, 'handed_over_at' => now(), 'ai_paused_at' => now()]);
            OperatorAssignment::where('conversation_id', $vc->conversation_id)->where('is_active', true)->update(['is_active' => false, 'released_at' => now()]);
            $assignment = OperatorAssignment::create([
                'operator_id' => $operator->id,
                'conversation_id' => $vc->conversation_id,
                'virtual_profile_id' => $vc->virtual_profile_id,
                'assigned_at' => now(),
                'is_active' => true,
            ]);
            $this->audit->log('operator.takeover', $operator, $vc);

            return $assignment;
        });
    }

    public function pause(VirtualConversation $vc, User $operator): bool
    {
        $ok = $vc->update(['status' => VirtualConversationStatus::Paused, 'ai_paused_at' => now()]);
        $this->audit->log('operator.pause', $operator, $vc);

        return $ok;
    }

    public function resume(VirtualConversation $vc, User $operator): bool
    {
        $ok = $vc->update(['status' => VirtualConversationStatus::Active, 'ai_paused_at' => null]);
        $this->audit->log('operator.resume', $operator, $vc);

        return $ok;
    }

    public function assign(Conversation $conversation, User $operator, ?int $virtualProfileId = null): OperatorAssignment
    {
        return DB::transaction(function () use ($conversation, $operator, $virtualProfileId) {
            OperatorAssignment::where('conversation_id', $conversation->id)->where('is_active', true)->update(['is_active' => false, 'released_at' => now()]);

            $assignment = OperatorAssignment::create([
                'operator_id' => $operator->id,
                'conversation_id' => $conversation->id,
                'virtual_profile_id' => $virtualProfileId,
                'assigned_at' => now(),
                'is_active' => true,
            ]);
            $this->audit->log('operator.assign', $operator, $conversation);

            return $assignment;
        });
    }

    public function close(VirtualConversation $vc, User $operator): bool
    {
        return DB::transaction(function () use ($vc, $operator) {
            $ok = $vc->update(['status' => VirtualConversationStatus::Closed]);
            OperatorAssignment::where('conversation_id', $vc->conversation_id)->where('is_active', true)->update(['is_active' => false, 'released_at' => now()]);
            $this->audit->log('operator.close', $operator, $vc);

            return $ok;
        });
    }
}
