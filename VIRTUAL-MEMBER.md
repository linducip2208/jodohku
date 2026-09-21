# VIRTUAL-MEMBER — Transparansi & Operasi

## Prinsip

- Akun `virtual`/`ai` **bukan orang sungguhan**; setiap profil virtual mencantumkan bio transparan + setiap pesan otomatis ditempeli label (`VirtualMemberService::label`: "Profil Virtual — dikelola Jodohku/operator" / "AI Persona — dikelola otomatis").
- Seed: `VirtualMemberSeeder` (6 persona fiksi + 3 `ai_personalities` + foto placeholder `placeholders/virtual/*`).

## Trigger (`ChatTriggerService`, `VirtualMemberService::handleEvent`, `TriggerSeeder`)

- Event: `user.registered`, `profile.liked`, `match.created`, `user.inactive_7d` (+ `chat_templates` + `chat_trigger_actions`).
- Guard: fitur `FEATURE_VIRTUAL`, trigger aktif, cap 5 virtual-conversation/hari/user, jam 08–22, cooldown per-trigger, profil virtual aktif tersedia.
- Mode: `template` (default aman), `hybrid` (25% AI), `ai` (fallback template bila gagal).

## Operator (`OperatorService`, `VirtualConversation`)

- `takeover` → `operator_id`, `status=transferred`, `handed_over_at` + **`ai_paused_at=now()`** (AI berhenti).
- `pause` → `paused` + `ai_paused_at`; `resume` → `active` + `ai_paused_at=null`; `close` → `closed` + lepas assignment.
- Diuji di `OperatorTakeoverPausesAiTest` dan `VirtualTriggerCreatesAutoChatTest`.
