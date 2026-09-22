<?php

namespace App\Services;

use App\Enums\CourtshipStage;
use App\Enums\CourtshipStatus;
use App\Models\Block;
use App\Models\ConversationMember;
use App\Models\Courtship;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Support\Facades\DB;

class CourtshipService
{
    public function __construct(protected AuditService $audit) {}

    protected function guardPair(User $a, User $b): void
    {
        if ((int) $a->id === (int) $b->id) {
            throw new \InvalidArgumentException('Cannot start a courtship with yourself.');
        }
        if (Block::existsBetween((int) $a->id, (int) $b->id)) {
            throw new \RuntimeException('Courtship blocked between these users.');
        }
    }

    protected function activeMatch(User $a, User $b): UserMatch
    {
        [$u1, $u2] = UserMatch::canonical((int) $a->id, (int) $b->id);
        $match = UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->where('is_active', true)->first();
        if (! $match) {
            throw new \RuntimeException('Courtship requires an active match first.');
        }

        return $match;
    }

    public function start(User $initiator, User $partner, array $data = []): Courtship
    {
        $this->guardPair($initiator, $partner);
        $match = $this->activeMatch($initiator, $partner);

        return DB::transaction(function () use ($initiator, $partner, $match, $data) {
            $exists = Courtship::where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('initiator_id', $initiator->id)->where('partner_id', $partner->id))
                ->orWhere(fn ($qq) => $qq->where('initiator_id', $partner->id)->where('partner_id', $initiator->id)))
                ->where('status', CourtshipStatus::Active)->exists();
            if ($exists) {
                throw new \RuntimeException('An active courtship already exists between you.');
            }
            $courtship = Courtship::create([
                'initiator_id' => $initiator->id,
                'partner_id' => $partner->id,
                'match_id' => $match->id,
                'conversation_id' => $data['conversation_id'] ?? null,
                'stage' => CourtshipStage::Kenalan,
                'status' => CourtshipStatus::Active,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'guardian_relation' => $data['guardian_relation'] ?? null,
                'stage_history' => [['stage' => CourtshipStage::Kenalan->value, 'at' => now()->toDateTimeString(), 'by' => $initiator->id]],
            ]);
            $this->audit->log('courtship.started', $initiator, $courtship, [], ['partner_id' => $partner->id]);

            return $courtship->fresh();
        });
    }

    public function setGuardian(Courtship $courtship, User $user, array $data): Courtship
    {
        $this->ensureActive($courtship);
        $courtship->update([
            'guardian_name' => $data['guardian_name'] ?? $courtship->guardian_name,
            'guardian_phone' => $data['guardian_phone'] ?? $courtship->guardian_phone,
            'guardian_relation' => $data['guardian_relation'] ?? $courtship->guardian_relation,
            'guardian_approved_at' => null,
        ]);
        $this->audit->log('courtship.guardian_set', $user, $courtship);

        return $courtship->fresh();
    }

    public function approveGuardian(Courtship $courtship, User $user): Courtship
    {
        $this->ensureActive($courtship);
        if (! $courtship->guardian_name) {
            throw new \RuntimeException('Set guardian details first.');
        }
        $courtship->update(['guardian_approved_at' => now()]);
        $this->audit->log('courtship.guardian_approved', $user, $courtship);

        return $courtship->fresh();
    }

    public function advance(Courtship $courtship, User $user): Courtship
    {
        $this->ensureActive($courtship);
        $next = $courtship->stage->next();
        if (! $next) {
            throw new \RuntimeException('Courtship already at the final stage.');
        }
        // Bureau rule: khitbah requires an approved guardian.
        if ($next === CourtshipStage::Khitbah && ! $courtship->guardian_approved_at) {
            throw new \RuntimeException('Guardian approval is required before khitbah.');
        }

        return DB::transaction(function () use ($courtship, $user, $next) {
            $history = $courtship->stage_history ?? [];
            $history[] = ['from' => $courtship->stage->value, 'to' => $next->value, 'at' => now()->toDateTimeString(), 'by' => $user->id];
            $courtship->update(['stage' => $next, 'stage_history' => $history]);
            if ($next->isFinal()) {
                $courtship->update(['status' => CourtshipStatus::Completed, 'completed_at' => now()]);
            }
            $this->audit->log('courtship.advanced', $user, $courtship, [], ['stage' => $next->value]);

            return $courtship->fresh();
        });
    }

    /**
     * Add a guardian (wali) as a READ-ONLY chaperone to the courtship chat.
     * Available from the taaruf stage onward, per bureau practice.
     */
    public function addChaperone(Courtship $courtship, User $guardian, User $actor): Courtship
    {
        $this->ensureActive($courtship);
        if (! in_array($courtship->stage, [CourtshipStage::Taaruf, CourtshipStage::Khitbah], true)) {
            throw new \RuntimeException('Chaperone joins from the taaruf stage.');
        }
        if ((int) $guardian->id === (int) $courtship->initiator_id || (int) $guardian->id === (int) $courtship->partner_id) {
            throw new \InvalidArgumentException('Chaperone must be a third party.');
        }
        if (Block::existsBetween((int) $guardian->id, (int) $courtship->initiator_id)
            || Block::existsBetween((int) $guardian->id, (int) $courtship->partner_id)) {
            throw new \RuntimeException('Chaperone is blocked by a party.');
        }

        return DB::transaction(function () use ($courtship, $guardian, $actor) {
            $conversation = $courtship->conversation;
            if (! $conversation) {
                $conversation = app(ChatService::class)->findOrCreateDirect($courtship->initiator, $courtship->partner);
                $courtship->update(['conversation_id' => $conversation->id]);
            }
            ConversationMember::updateOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $guardian->id],
                ['role' => 'chaperone', 'joined_at' => now(), 'left_at' => null]
            );
            $this->audit->log('courtship.chaperone_added', $actor, $courtship, [], ['guardian_id' => $guardian->id]);

            return $courtship->fresh(['conversation']);
        });
    }

    public function removeChaperone(Courtship $courtship, User $guardian, User $actor): Courtship
    {
        $this->ensureActive($courtship);

        return DB::transaction(function () use ($courtship, $guardian, $actor) {
            if ($courtship->conversation_id) {
                ConversationMember::where('conversation_id', $courtship->conversation_id)
                    ->where('user_id', $guardian->id)->where('role', 'chaperone')->delete();
            }
            $this->audit->log('courtship.chaperone_removed', $actor, $courtship, [], ['guardian_id' => $guardian->id]);

            return $courtship->fresh();
        });
    }

    public function withdraw(Courtship $courtship, User $user): Courtship
    {
        $this->ensureActive($courtship);
        $courtship->update(['status' => CourtshipStatus::Withdrawn, 'completed_at' => now()]);
        $this->audit->log('courtship.withdrawn', $user, $courtship);

        return $courtship->fresh();
    }

    public function forUser(User $user, int $perPage = 20)
    {
        return Courtship::where(fn ($q) => $q->where('initiator_id', $user->id)->orWhere('partner_id', $user->id))
            ->with(['initiator', 'partner'])->latest('id')->paginate($perPage);
    }

    protected function ensureActive(Courtship $courtship): void
    {
        if ($courtship->status !== CourtshipStatus::Active) {
            throw new \RuntimeException('Courtship is no longer active.');
        }
    }
}
