<?php

namespace App\Services;

class ScamDetectionService
{
    protected array $investmentKeywords = [
        'investasi', 'investment', 'trading', 'forex', 'crypto', 'bitcoin', 'usdt',
        'profit harian', 'profit daily', 'jaminan profit', 'pasti untung', 'keuntungan',
        'deposit', 'withdraw', 'wd cepat', 'modal kecil', 'cuan', 'sinyal trading',
    ];

    protected array $credentialKeywords = [
        'password', 'kata sandi', 'otp', 'kode otp', 'kode verifikasi', 'pin atm',
        'nomor kartu', 'cvv', 'token bank', 'username bank', 'm-banking',
    ];

    /** @return array{score:int, flags:string[]} */
    public function analyze(string $text): array
    {
        $flags = [];
        $score = 0;
        $lower = mb_strtolower($text);

        // Phone numbers (ID + international)
        if (preg_match('/(\+?62[\s\-]?\d[\d\s\-]{7,14}\d|08[\d\s\-]{8,14}|\+?\d[\d\s\-]{9,15})/', $text)) {
            $flags[] = 'phone_number';
            $score += 25;
        }
        // Email
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)) {
            $flags[] = 'email_address';
            $score += 20;
        }
        // Telegram / WhatsApp / Line / IG invites
        if (preg_match('/\b(telegram|tele|wa\b|whatsapp|line\s?id|dm\s+ig|instagram|inbox\s+fb)\b/i', $text)
            || preg_match('/t\.me\/[\w_]+/i', $text)) {
            $flags[] = 'offplatform_invite';
            $score += 20;
        }
        // Suspicious URLs (shorteners, non-https, IP hosts)
        if (preg_match('/\b(bit\.ly|tinyurl|t\.co|s\.id|goo\.gl|cutt\.ly|linktr\.ee)\b/i', $text)) {
            $flags[] = 'url_shortener';
            $score += 20;
        }
        if (preg_match('/https?:\/\/\d+\.\d+\.\d+\.\d+/i', $text)) {
            $flags[] = 'ip_url';
            $score += 25;
        }
        if (preg_match('/https?:\/\/[^\s]+\.(tk|ml|ga|cf|gq|xyz|top|club)(\b|\/)/i', $text)) {
            $flags[] = 'suspicious_tld';
            $score += 20;
        }
        // Investment / money lure
        foreach ($this->investmentKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $flags[] = 'investment_lure:'.$kw;
                $score += 15;
                break;
            }
        }
        if (preg_match('/\b(transfer|kirim\s+uang|bayar\s+dulu|admin\s+fee|fee\s+\d|bonus\s+\d+%|gajian|hadiah\s+uang)\b/i', $text)) {
            $flags[] = 'money_request';
            $score += 25;
        }
        // Credential phishing
        foreach ($this->credentialKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $flags[] = 'credential_phish:'.$kw;
                $score += 30;
                break;
            }
        }
        // Urgency / threat
        if (preg_match('/\b(segera|sekarang\s+juga|terakhir\s+hari\s+ini|akun\s+diblokir|verifikasi\s+ulang)\b/i', $text)) {
            $flags[] = 'urgency';
            $score += 10;
        }

        return ['score' => min(100, $score), 'flags' => array_values(array_unique($flags))];
    }
}
