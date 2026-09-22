<?php

namespace App\Services;

use App\Models\FraudEvent;
use App\Models\FraudRiskScore;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FraudDetectionService
{
    /** @return array{score:int, level:string, signals:array} */
    public function scoreUser(User $user, array $context = []): array
    {
        $signals = [];
        $score = 0;
        $ip = $context['ip'] ?? request()->ip();
        $device = $context['device'] ?? request()->userAgent();

        // Velocity: accounts created from same IP in last 24h
        if ($ip) {
            $sameIp = FraudRiskScore::where('ip_address', $ip)->where('scored_at', '>', now()->subDay())->count();
            if ($sameIp >= 5) {
                $signals['multi_account_ip'] = $sameIp;
                $score += 30;
            } elseif ($sameIp >= 2) {
                $signals['shared_ip'] = $sameIp;
                $score += 12;
            }
        }

        // Message velocity: >100 msgs/hour
        $msgHour = Message::where('sender_id', $user->id)->where('created_at', '>', now()->subHour())->count();
        if ($msgHour > 100) {
            $signals['msg_velocity'] = $msgHour;
            $score += 25;
        } elseif ($msgHour > 50) {
            $signals['msg_velocity'] = $msgHour;
            $score += 10;
        }

        // Profile similarity: same display name / no photo / empty bio
        if (! $user->avatar_path && ! $user->photos()->exists()) {
            $signals['no_photo'] = true;
            $score += 10;
        }
        $user->loadMissing(['profile']);
        if (! $user->profile || empty($user->profile->bio)) {
            $signals['empty_bio'] = true;
            $score += 8;
        }
        if ($user->created_at && $user->created_at->gt(now()->subHour()) && $msgHour > 10) {
            $signals['new_account_spam'] = true;
            $score += 20;
        }
        // Device reuse (lookup and storage must use the same truncation)
        $fingerprint = $device ? mb_substr((string) $device, 0, 500) : null;
        if ($fingerprint) {
            $sameDevice = FraudRiskScore::where('device_fingerprint', $fingerprint)
                ->where('user_id', '!=', $user->id)->count();
            if ($sameDevice > 0) {
                $signals['device_reuse'] = $sameDevice;
                $score += 15;
            }
        }

        $score = min(100, $score);
        $level = $score >= 70 ? 'high' : ($score >= 35 ? 'medium' : 'low');

        DB::transaction(function () use ($user, $score, $level, $signals, $ip, $fingerprint) {
            FraudRiskScore::create([
                'user_id' => $user->id,
                'score' => $score,
                'level' => $level,
                'signals' => $signals,
                'ip_address' => $ip,
                'device_fingerprint' => $fingerprint,
                'scored_at' => now(),
            ]);
            if ($level !== 'low') {
                FraudEvent::create([
                    'user_id' => $user->id,
                    'event_type' => 'risk_scored_'.$level,
                    'description' => 'Fraud risk '.$level.' ('.$score.')',
                    'severity' => $level === 'high' ? 3 : 2,
                    'metadata' => $signals,
                    'ip_address' => $ip,
                ]);
            }
        });

        return ['score' => $score, 'level' => $level, 'signals' => $signals];
    }
}
