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
    public function analyze(string $text, ?string $normalized = null): array
    {
        $flags = [];
        $score = 0;
        // Run on BOTH raw and normalized text: normalized unfolds hxxp,
        // zero-width, spaced letters, and Indonesian number-words.
        $variants = [$text];
        if ($normalized !== null && $normalized !== '' && $normalized !== $text) {
            $variants[] = $normalized;
        } elseif ($normalized === null) {
            $variants[] = $this->quickNormalize($text);
        }
        $raw = $text;
        $folded = end($variants);

        // Phone numbers (ID + international with explicit country code; plain digit
        // strings no longer match to avoid false positives on ordinary numbers)
        // Check raw AND folded (folded catches "nol delapan..." + defang).
        foreach ([$raw, $folded] as $variant) {
            if (preg_match('/(\+62[\s\-.]?\d[\s\d\-.]{7,14}\d|08[\d\s\-.]{8,14}|\+\d[\d\s\-.]{8,14}\d)/', (string) $variant)) {
                $flags[] = 'phone_number';
                $score += 25;
                break;
            }
        }
        // Email
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text) || preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', (string) $folded)) {
            $flags[] = 'email_address';
            $score += 20;
        }
        // Telegram / WhatsApp / Line / IG invites
        if (preg_match('/\b(telegram|tele|wa\b|whatsapp|line\s?id|dm\s+ig|instagram|inbox\s+fb)\b/i', $text)
            || preg_match('/\b(telegram|tele|wa\b|whatsapp|line\s?id|dm\s+ig|instagram|inbox\s+fb)\b/i', (string) $folded)
            || preg_match('/t\.me\/[\w_]+/i', $text) || preg_match('/t\.me\/[\w_]+/i', (string) $folded)) {
            $flags[] = 'offplatform_invite';
            $score += 20;
        }
        // Suspicious URLs (shorteners incl. defanged "bit dot ly", non-https, IP hosts)
        if (preg_match('/\b(bit\.ly|tinyurl|t\.co|s\.id|goo\.gl|cutt\.ly|linktr\.ee)\b/i', $text)
            || preg_match('/\b(bit\.ly|tinyurl|t\.co|s\.id|goo\.gl|cutt\.ly|linktr\.ee)\b/i', (string) $folded)) {
            $flags[] = 'url_shortener';
            $score += 20;
        }
        if (preg_match('/https?:\/\/\d+\.\d+\.\d+\.\d+/i', $text) || preg_match('/https?:\/\/\d+\.\d+\.\d+\.\d+/i', (string) $folded)) {
            $flags[] = 'ip_url';
            $score += 25;
        }
        // Defanged URL: hxxp / "titik" unfolding already in $folded.
        if (preg_match('/https?:\/\/[^\s]+\.(tk|ml|ga|cf|gq|xyz|top|club)(\b|\/)/i', $text)
            || preg_match('/https?:\/\/[^\s]+\.(tk|ml|ga|cf|gq|xyz|top|club)(\b|\/)/i', (string) $folded)) {
            $flags[] = 'suspicious_tld';
            $score += 20;
        }
        if (preg_match('/\bhxxps?:\/\//i', $text)) {
            $flags[] = 'defanged_url';
            $score += 20;
        }
        // Investment / money lure (every matched keyword counts; capped by min(100))
        $lowerRaw = mb_strtolower($text);
        $lowerFolded = mb_strtolower((string) $folded);
        foreach ($this->investmentKeywords as $kw) {
            if (str_contains($lowerRaw, $kw) || str_contains($lowerFolded, $kw)) {
                $flags[] = 'investment_lure:'.$kw;
                $score += 15;
            }
        }
        if (preg_match('/\b(transfer|kirim\s+uang|bayar\s+dulu|admin\s+fee|fee\s+\d|bonus\s+\d+%|gajian|hadiah\s+uang)\b/i', $text)
            || preg_match('/\b(transfer|kirim\s+uang|bayar\s+dulu|admin\s+fee|fee\s+\d|bonus\s+\d+%|gajian|hadiah\s+uang)\b/i', (string) $folded)) {
            $flags[] = 'money_request';
            $score += 25;
        }
        // Credential phishing (every matched keyword counts; capped by min(100))
        foreach ($this->credentialKeywords as $kw) {
            if (str_contains($lowerRaw, $kw) || str_contains($lowerFolded, $kw)) {
                $flags[] = 'credential_phish:'.$kw;
                $score += 30;
            }
        }
        // Urgency / threat
        if (preg_match('/\b(segera|sekarang\s+juga|terakhir\s+hari\s+ini|akun\s+diblokir|verifikasi\s+ulang)\b/i', $text)
            || preg_match('/\b(segera|sekarang\s+juga|terakhir\s+hari\s+ini|akun\s+diblokir|verifikasi\s+ulang)\b/i', (string) $folded)) {
            $flags[] = 'urgency';
            $score += 10;
        }

        return ['score' => min(100, $score), 'flags' => array_values(array_unique($flags))];
    }

    protected function quickNormalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        $text = (string) preg_replace('/\bhxxps?\b/u', 'http', $text);
        $text = (string) preg_replace('/\[\s*\.\s*\]|\(\s*dot\s*\)|\btitik\b|\bdot\b/u', '.', $text);
        $map = ['nol' => '0', 'satu' => '1', 'dua' => '2', 'tiga' => '3', 'empat' => '4', 'lima' => '5', 'enam' => '6', 'tujuh' => '7', 'delapan' => '8', 'sembilan' => '9'];
        foreach ($map as $word => $digit) {
            $text = (string) preg_replace('/\b'.preg_quote($word, '/').'\b/u', $digit, $text);
        }

        return $text;
    }
}
