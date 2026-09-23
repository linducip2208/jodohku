# AI — Provider, Guardrail, Biaya

## Abstraksi (`config/ai.php`, `app/AI/*`, `app/Services/Ai*.php`)

- Provider: `openai` (`OPENAI_BASE_URL/KEY/MODEL`) + `compatible` (`AI_BASE_URL/KEY/MODEL`, OpenAI-compatible) via `AiProviderManager`; default `AI_PROVIDER/AI_MODEL`.
- DB: `ai_providers`, `ai_models` (cost/1k, max_tokens, default), `ai_personalities` (system_prompt, tone, lang), `ai_usage_logs` (token, cost, latency, purpose).
- Layanan: `AiService::chat` (rate-limit `AI_RATE_PER_MIN/DAY`), `AiChatAssistantService`, `AiMatchmakerService` (grounded ke profil), `AiModerationService::review` (hanya bila flagged), `AiCostService`.

## Guardrail

- Chat: `refuse_secrets`, `grounded_only`, `max_profiles_per_answer=10`; moderasi lokal dulu, AI tidak pernah memblokir langsung.
- Virtual: mode template default; AI dipakai bila `mode=ai/hybrid` dengan fallback template bila gagal; label transparan wajib.
- Rate-limit + budget (`settings ai.monthly_budget_idr`), semua pemakaian dicatat di `ai_usage_logs`.

## Biaya

`AiModel::estimateCost(input,output)`; monitor via `ai_usage_logs.cost`. Seed: `gpt-4o-mini` default (0.00015/0.0006 per 1k).

## Grounding & asisten (Intelligence)

- Matchmaker: prompt hanya berisi kandidat NYATA dari `DiscoveryService` +
  alasan `MatchExplanation`; instruksi eksplisit jangan mengarang.
- `profileTips`: checklist deterministik dari gap profil nyata (selalu ada) +
  parafrasa AI best-effort; `digest`/`catchUp` punya fallback ekstraktif.
- AI tidak pernah: mengarang atribut/verifikasi/lokasi/skor, membuka data
  privat, mengklaim kepastian, atau memutuskan jodoh.
