<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\ModerationQueue;

/**
 * Used ONLY when MessageModerationService flags needsAiReview().
 * Second-opinion classifier; never the first line of defense.
 */
class AiModerationService
{
    public function __construct(protected AiService $ai) {}

    /** @return array{verdict:string, confidence:float, reason:string} */
    public function review(Message $message): array
    {
        $body = (string) $message->body;
        $prompt = "Kamu moderator keamanan aplikasi kencan. Klasifikasikan pesan berikut sebagai allow|warn|block dengan confidence 0-1 dan alasan singkat.\n".
            "Fokus: penipuan, ajakan pindah platform untuk menipu, permintaan uang/kredensial, pelecehan.\nPesan: \"{$body}\"\n".
            'Jawab format: VERDICT=<allow|warn|block> CONF=<0-1> REASON=<singkat>';

        try {
            $res = $this->ai->chat($prompt, ['max_tokens' => 120, 'temperature' => 0.1], $message->sender, 'ai_moderation');
            $text = (string) $res['text'];
        } catch (\Throwable $e) {
            return ['verdict' => 'allow', 'confidence' => 0.0, 'reason' => 'ai-unavailable'];
        }

        $verdict = str_contains(strtolower($text), 'block') ? 'block' : (str_contains(strtolower($text), 'warn') ? 'warn' : 'allow');
        preg_match('/CONF\s*=\s*([0-9.]+)/i', $text, $m);
        $conf = isset($m[1]) ? min(1, max(0, (float) $m[1])) : 0.5;
        preg_match('/REASON\s*=\s*(.+)/i', $text, $r);

        $result = ['verdict' => $verdict, 'confidence' => $conf, 'reason' => trim($r[1] ?? $text)];

        if ($verdict !== 'allow') {
            ModerationQueue::create([
                'queueable_type' => Message::class,
                'queueable_id' => $message->id,
                'reported_by' => null,
                'reason' => 'ai-review: '.$verdict.' ('.$result['reason'].')',
                'priority' => $verdict === 'block' ? 9 : 6,
                'status' => 'pending',
            ]);
            if ($verdict === 'block') {
                $message->update(['status' => MessageStatus::Moderated]);
            }
        }

        return $result;
    }
}
