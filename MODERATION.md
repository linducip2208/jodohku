# MODERATION — Kebijakan & Operasional

## Sumber

- `profanity_categories/words` (ID+EN, severity, regex opsional) — seed `ProfanitySeeder`.
- `moderation_rules` (phone/off-platform/profanity-burst/phishing/investment; aksi warn/mute/escalate; threshold+jendela+prioritas).
- `ScamDetectionService` pola: `phone_number`, `email_address`, `offplatform_invite`, `url_shortener/ip_url/suspicious_tld`, `investment_lure`, `money_request`, `credential_phish`, `urgency`.

## Alur

Chat/API → `MessageModerationService` (tanpa AI) → keputusan + `moderation_logs` → antre `moderation_queue` bila block/flag → `ProcessMessageModeration` → `AiModerationService::review` bila `needs_ai` → moderator (`moderator` gate) putuskan via `ReportPolicy`/`ModerationAction` (warn/mute/suspend/ban/delete_content/escalate).

## Uji

`ProhibitedMessageModeratedTest`: profanity → `clean` termask; phone+WA+money → flag `phone_number`, `risk>0`, keputusan termoderasi.

## Kamus admin + aturan cache

Kamus ID/EN dikelola moderator via `/admin/moderation/words` (CRUD + `Dictionary` di sidebar); setiap perubahan bust cache. `ProfanityService` hanya cache **plain array** (`profanity_words:v2`) — model Eloquent dilarang melewati cache driver serializing (database/file/redis) karena unserialize menghasilkan `__PHP_Incomplete_Class` dan mematikan request (regression test di `ProfanityCensorTest`).
