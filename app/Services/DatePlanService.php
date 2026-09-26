<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\DatePlan;
use App\Models\User;
use App\Models\UserMatch;
use App\Notifications\DateProposed;
use Illuminate\Support\Facades\DB;

/**
 * "Ajak Kencan": match/chat → propose datetime+place → partner
 * accept/decline → H-24 reminder (SendDateReminders job).
 * Requires an existing relationship (match or shared conversation)
 * so strangers can't spam date invites.
 */
class DatePlanService
{
    public function __construct(protected AuditService $audit, protected NotificationService $notifications) {}

    public function propose(User $proposer, User $partner, array $data): DatePlan
    {
        if ((int) $proposer->id === (int) $partner->id) {
            throw new \InvalidArgumentException('Cannot date yourself.');
        }
        if (Block::existsBetween((int) $proposer->id, (int) $partner->id)) {
            throw new \RuntimeException('Tidak bisa mengajak user ini.');
        }
        [$a, $b] = UserMatch::canonical((int) $proposer->id, (int) $partner->id);
        $matched = UserMatch::where('user_a_id', $a)->where('user_b_id', $b)->exists();
        $conversation = Conversation::findDirect((int) $proposer->id, (int) $partner->id);
        if (! $matched && ! $conversation) {
            throw new \RuntimeException('Ajak kencan hanya untuk match atau teman chat.');
        }
        $when = $data['scheduled_at'] ?? null;
        if (! $when || $when->lt(now()->addHour())) {
            throw new \InvalidArgumentException('Jadwal minimal 1 jam dari sekarang.');
        }

        return DB::transaction(function () use ($proposer, $partner, $data, $when, $conversation) {
            $plan = DatePlan::create([
                'proposer_id' => $proposer->id,
                'partner_id' => $partner->id,
                'conversation_id' => $conversation?->id,
                'scheduled_at' => $when,
                'place' => isset($data['place']) ? mb_substr(trim((string) $data['place']), 0, 200) : null,
                'note' => isset($data['note']) ? mb_substr(trim((string) $data['note']), 0, 500) : null,
                'status' => DatePlan::STATUS_PROPOSED,
            ]);
            $this->audit->log('date.proposed', $proposer, $plan);
            try {
                $this->notifications->send($partner, new DateProposed($plan->fresh(['proposer'])));
            } catch (\Throwable) {
            }

            return $plan->fresh();
        });
    }

    public function respond(User $partner, DatePlan $plan, string $action): DatePlan
    {
        if ((int) $plan->partner_id !== (int) $partner->id) {
            throw new \RuntimeException('Hanya pasangan yang bisa merespons.');
        }
        if ($plan->status !== DatePlan::STATUS_PROPOSED) {
            throw new \RuntimeException('Ajakan sudah diproses.');
        }
        if (! in_array($action, ['accept', 'decline'], true)) {
            throw new \InvalidArgumentException('Invalid action.');
        }
        $plan->update(['status' => $action === 'accept' ? DatePlan::STATUS_ACCEPTED : DatePlan::STATUS_DECLINED]);
        $this->audit->log('date.'.$action.'ed', $partner, $plan);

        return $plan->fresh();
    }

    public function cancel(User $user, DatePlan $plan): DatePlan
    {
        if (! $plan->involves((int) $user->id)) {
            throw new \RuntimeException('Not your plan.');
        }
        if (in_array($plan->status, [DatePlan::STATUS_DECLINED, DatePlan::STATUS_CANCELLED], true)) {
            throw new \RuntimeException('Sudah selesai/dibatalkan.');
        }
        $plan->update(['status' => DatePlan::STATUS_CANCELLED]);
        $this->audit->log('date.cancelled', $user, $plan);

        return $plan->fresh();
    }

    /** Accepted plans within 24h that haven't been reminded. */
    public function dueForReminder(int $batch = 200)
    {
        return DatePlan::where('status', DatePlan::STATUS_ACCEPTED)
            ->whereNull('reminded_at')
            ->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addDay())
            ->with(['proposer', 'partner'])
            ->limit($batch)->get();
    }
}
