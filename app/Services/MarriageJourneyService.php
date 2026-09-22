<?php

namespace App\Services;

use App\Enums\ConsultationStatus;
use App\Enums\CourtshipStage;
use App\Enums\CourtshipStatus;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Courtship;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMatch;

class MarriageJourneyService
{
    protected array $stageOrder = ['kenalan', 'taaruf', 'khitbah', 'menikah'];

    /** Journey steps derived strictly from actual records — never fabricated. */
    public function journey(User $user, User $partner): array
    {
        [$u1, $u2] = UserMatch::canonical((int) $user->id, (int) $partner->id);
        $match = UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->where('is_active', true)->first();

        $conversation = Conversation::findDirect((int) $user->id, (int) $partner->id);
        $messageCount = $conversation ? Message::where('conversation_id', $conversation->id)->count() : 0;

        $courtship = Courtship::where(fn ($q) => $q
            ->where(fn ($qq) => $qq->where('initiator_id', $user->id)->where('partner_id', $partner->id))
            ->orWhere(fn ($qq) => $qq->where('initiator_id', $partner->id)->where('partner_id', $user->id)))
            ->where('status', CourtshipStatus::Active)->latest('id')->first()
            ?? Courtship::where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('initiator_id', $user->id)->where('partner_id', $partner->id))
                ->orWhere(fn ($qq) => $qq->where('initiator_id', $partner->id)->where('partner_id', $user->id)))
                ->latest('id')->first();

        $stageIdx = $courtship ? array_search($courtship->stage->value, $this->stageOrder, true) : false;
        $counseled = Consultation::where('user_id', $user->id)->where('status', ConsultationStatus::Completed)->exists();
        $family = (bool) ($courtship?->guardian_approved_at);
        $married = $courtship && ($courtship->status === CourtshipStatus::Completed || $courtship->stage === CourtshipStage::Menikah);

        $steps = [
            ['key' => 'match', 'label' => 'Match', 'done' => $match !== null, 'detail' => $match ? 'Saling suka sejak '.$match->matched_at?->format('d M Y') : 'Belum match'],
            ['key' => 'chat', 'label' => 'Chat', 'done' => $messageCount > 0, 'detail' => $messageCount.' pesan bertukar'],
            ['key' => 'perkenalan', 'label' => 'Perkenalan', 'done' => $stageIdx !== false && $stageIdx >= 0, 'detail' => $courtship ? 'Tahap: '.$courtship->stage->label() : 'Belum mulai taaruf'],
            ['key' => 'taaruf', 'label' => 'Taaruf', 'done' => $stageIdx !== false && $stageIdx >= 1, 'detail' => $courtship ? 'Tahap: '.$courtship->stage->label() : 'Belum mulai taaruf'],
            ['key' => 'konseling', 'label' => 'Konseling', 'done' => $counseled, 'detail' => $counseled ? 'Konsultasi selesai diikuti' : 'Belum ada konsultasi selesai'],
            ['key' => 'keluarga', 'label' => 'Keluarga', 'done' => $family, 'detail' => $family ? 'Wali sudah menyetujui' : 'Restu wali belum tercatat'],
            ['key' => 'menikah', 'label' => 'Menuju Pernikahan', 'done' => $married, 'detail' => $married ? 'MasyaAllah, semoga samawa!' : 'Perjalanan masih berlanjut'],
        ];

        $next = null;
        foreach ($steps as $s) {
            if (! $s['done']) {
                $next = $s;
                break;
            }
        }

        return [
            'user_id' => $user->id,
            'partner_id' => $partner->id,
            'courtship_id' => $courtship?->id,
            'conversation_id' => $conversation?->id,
            'steps' => $steps,
            'completed' => count(array_filter($steps, fn ($s) => $s['done'])),
            'total' => count($steps),
            'next_step' => $next,
            'counseling_suggested' => $stageIdx !== false && $stageIdx >= 1 && ! $counseled,
        ];
    }
}
