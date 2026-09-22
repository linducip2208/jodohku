<?php

namespace App\Services;

use App\Enums\ModerationAction;
use App\Models\Message;
use App\Models\ModerationLog;
use App\Models\ModerationQueue;
use App\Models\Setting;
use App\Models\User;

class MessageModerationService
{
    public function __construct(
        protected ProfanityService $profanity,
        protected ScamDetectionService $scam,
    ) {}

    /**
     * Local-first moderation pipeline.
     * NEVER calls AI here.
     *
     * @return array{decision:string, risk:int, clean:string, flags:string[], needs_ai:bool}
     *                                                                                       decision: allow|mask|warning|block|flag
     */
    public function moderate(string $body, ?User $sender = null, ?Message $message = null): array
    {
        $flags = [];
        $risk = 0;

        $prof = $this->profanity->censor($body);
        $clean = $prof['clean'];
        $hitCount = $prof['count'] + count($prof['obfuscated'] ?? []);
        if ($hitCount > 0) {
            $cat = $prof['categories'][0] ?? 'profanity';
            $flags[] = $cat.':'.implode(',', array_slice(array_merge($prof['hits'], array_map(fn ($w) => $w.'~', $prof['obfuscated'] ?? [])), 0, 5));
            // High-severity categories (sexual/hate) escalate much faster.
            $risk += $prof['max_severity'] >= 4 ? 30 + $hitCount * 5 : min(40, 12 + $hitCount * 8);
        }

        $normalized = $this->profanity->normalize($body);
        $scam = $this->scam->analyze($body, $normalized);
        foreach ($scam['flags'] as $f) {
            $flags[] = $f;
        }
        $risk += (int) round($scam['score'] * 0.8);
        $risk = min(100, $risk);

        // Length / spam burst heuristic
        if (mb_strlen($body) > 1500) {
            $flags[] = 'excessive_length';
            $risk += 5;
        }
        if (preg_match('/(.)\1{7,}/u', $body)) {
            $flags[] = 'char_spam';
            $risk += 8;
        }

        $blockAt = max(1, (int) Setting::get('auto_block_risk', 85, 'moderation'));
        $flagAt = max(1, (int) Setting::get('auto_flag_risk', 60, 'moderation'));
        $warnAt = max(1, (int) Setting::get('warn_risk', 35, 'moderation'));
        $decision = match (true) {
            $risk >= $blockAt => 'block',
            $risk >= $flagAt => 'flag',
            $risk >= $warnAt => 'warning',
            $hitCount > 0 => 'mask',
            default => 'allow',
        };

        $needsAi = $this->needsAiReview($risk, $flags);

        ModerationLog::create([
            'moderator_id' => null,
            'target_user_id' => $sender?->id,
            'moderatable_type' => $message ? Message::class : null,
            'moderatable_id' => $message?->id,
            'action' => match ($decision) {
                'block' => ModerationAction::DeleteContent,
                'flag' => ModerationAction::Escalate,
                'warning' => ModerationAction::Warn,
                default => ModerationAction::None,
            },
            'reason' => 'auto:risk='.$risk.' flags='.implode('|', array_slice($flags, 0, 8)),
            'metadata' => ['risk' => $risk, 'flags' => $flags, 'decision' => $decision, 'needs_ai' => $needsAi],
        ]);

        if (in_array($decision, ['block', 'flag'], true)) {
            ModerationQueue::create([
                'queueable_type' => $message ? Message::class : User::class,
                'queueable_id' => $message?->id ?? $sender?->id ?? 0,
                'reported_by' => null,
                'reason' => 'auto-moderation risk='.$risk,
                'priority' => $risk >= 85 ? 10 : 5,
                'status' => 'pending',
            ]);
        }

        return [
            'decision' => $decision,
            'risk' => min(100, $risk),
            'clean' => $decision === 'mask' || $decision === 'warning' ? $clean : $body,
            'flags' => $flags,
            'needs_ai' => $needsAi,
        ];
    }

    public function needsAiReview(int $risk, array $flags = []): bool
    {
        if (! Setting::get('ai_review_enabled', true, 'moderation')) {
            return false;
        }
        if ($risk >= 60) {
            return true;
        }
        foreach (['credential_phish', 'investment_lure', 'ip_url'] as $needle) {
            foreach ($flags as $f) {
                if (str_starts_with($f, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
