# SCALE AUDIT — Jodohku (architecture audit, no code changed)

Date: 2026-09-24 · HEAD: `53c4414` · Scope: million-user readiness.
Method: static inspection of composer/routes/controllers/services/models/
migrations/jobs/events/policies/middleware/commands/config/tests/deployment,
plus measured query counts on a 5,003-user MySQL dataset (`perf.php`:
discover p1 163ms/12q, filtered 151ms/10q, dailyPicks 384ms/32q, inbox
12ms/5q, notifications 3ms/1q). No benchmarks fabricated; these are the only
measured figures in this document.

## 1. Current architecture

```text
                    ┌─────────────┐
                    │   Clients   │── Blade/Livewire/Alpine ──┐
                    └─────────────┘   Sanctum API /api/v1 ────┤
                                                              ▼
                        ┌─────────────────────────────────────────┐
                        │  Laravel 13 (single app, no services)   │
                        │  Controllers → Services → Eloquent      │
                        │  Policies/Gates (member→superadmin)     │
                        └──────┬──────────────┬─────────┬─────────┘
                               │              │         │
                ┌──────────────▼──┐  ┌────────▼───┐  ┌──▼──────────────┐
                │  MySQL 8 (ALL)  │  │ Reverb WS  │  │ Local disks     │
                │  app + queue +  │  │ single node│  │ public/chat;    │
                │  cache + sess.  │  │ presence   │  │ S3 via env only │
                └─────────────────┘  └────────────┘  └─────────────────┘
```

- Queue/cache/session default to **database** (`.env.example`:
  `QUEUE_CONNECTION=database`, `CACHE_STORE=database`,
  `SESSION_DRIVER=database`). Redis config exists but is opt-in.
- All 14 notifications implement `ShouldQueue`; all 16 domain events
  implement `ShouldBroadcast` (Reverb, single node, no horizontal story).
- Matching: `MatchingEngine` (8 normalized dims, hard filter block/status) +
  `DiscoveryService` (filter → pool → in-memory score → boost only reorders)
  + `MatchScore` read-path (`scoreMany`, TTL recompute) + versioned weights.
- Chat: `ChatService` (idempotent `client_message_id`, per-peer quota,
  local-first moderation → `ModerationLog` row + optional AI review job).
- Payments: 4 adapters (iPaymu/Xendit/Midtrans/Tripay) with HMAC/callback
  verification, idempotent fulfill with row locks, gateway-first refunds.
- Taaruf: `CourtshipService` (row-locked advance, anti-self-approve guardian,
  portable overlap check for consultations).
- SEO/PSEO: cached sitemap sections, quality-gated city pages, opt-in `/u/*`.

## 2. Current bottlenecks (evidenced)

1. **Single MySQL does everything** (app + jobs + cache + sessions).
2. **Discovery pool is full-row + in-memory scoring** (163ms/12q @5k; cost
   grows with pool size, not page size; deep pages re-walk the pool).
3. **`match_scores` grows per viewed pair**, no TTL cleanup.
4. **Per-message sync writes**: `ModerationLog::create` on EVERY message +
   message row + `last_message_at` + broadcast in the send path.
5. **`notifications` (default Laravel) has ZERO indexes**, grows forever.
6. **Admin analytics uncached** (12 methods, raw COUNTs); dashboard IS cached.
7. **Profile visitors write-never**: `ProfileViewed` fires, nothing persists
   `profile_views` in production — dead read path.
8. **AI cost log-only**: per-user/min/day limits, no spend cap/kill-switch.

## 3. Critical risks

- **C1. DB-is-everything** (jobs/cache/session contend with product tables).
- **C2. Reverb single node** (no Redis adapter/LB plan configured).
- **C3. Unbounded tables**: notifications, audit_logs, jobs, sessions,
  match_scores, ai_usage_logs (no pruning/retention).
- **C4. Geo bounding box on unindexed floats** (full-scan shape at 1M).
- **C5. `LIKE %kw%` search**, no index infrastructure (no Scout in composer).
- **C6. Cold start** (empty match_scores; no precompute on register).
- **C7. Liquidity** (small-city collapse; static daily picks only).

## 4. Database risks

- Missing indexes: notifications morph+time (**highest value**); users
  geo/flags; matches `(user_a,is_active)+(user_b,is_active)`; audit_logs time.
- Covered: messages, likes, match_scores pair+user/score, courtships,
  consultations, events, forums, blocks, payments.
- Integrity verified on demo data: 0 orphans, 0 duplicate likes/scores/
  invoices (unique constraints hold).
- No partitioning; no read replicas; FK cascades correct (reset-safe).

## 5. Discovery risks

- Pool-before-score means filter selectivity directly sets cost; low-selectivity
  filters (e.g. new city) scan wide pools.
- `scoreMany` upserts per miss — write amplification per discovery until warm.
- Leftover-carry cursor is correct but embeds up to 2×perPage ids in URLs.
- dailyPicks runs 2× candidatesFor synchronously in request (384ms @5k).

## 6. Realtime risks

- Single Reverb node; presence channels; no connection ceiling measured.
- `TypingIndicator` broadcasts per keystroke-ish event (client-throttled?).
- Reconnect/duplicate-event handling relies on client Echo defaults.
- Per-message broadcast + notification job per message (storm risk at volume).

## 7. Queue risks

- Database driver: lock contention, no priority lanes, polling delay.
- Zero jobs define `tries/timeout/backoff` (verified); poison message =
  infinite retry via default attempts.
- `failed_jobs` exists but no alerting/ops runbook.
- `BroadcastMessage` fan-out loops users synchronously in chunks.

## 8. Search risks

- `LIKE %kw%` on users/profile/bio + forums/posts; full scans.
- No stemming/typo tolerance (Indonesian morphology unhandled).
- Verdict: MySQL sufficient until ~100k users; then Meilisearch+Scout
  (self-hostable). No Elasticsearch/OpenSearch (unjustified).

## 9. AI risks

- Guardrails + allowlisted models + per-purpose token caps + PII stripping
  implemented; quota atomic per-min/day with premium multiplier.
- Gaps: no spend cap/kill-switch, no cost anomaly alert, provider failover
  absent (single configured provider), token/cost logged but unbounded table.
- Prompt PII: history/digest sent fully; consent granular per feature but no
  central redaction log.

## 10. Payment risks

- Implemented well: per-gateway HMAC/callback-token + `hash_equals`,
  idempotent webhook via `event_id` + `lockForUpdate`, fulfill-once,
  gateway-first refunds, credit clawback capped at balance, secrets via
  `Crypt`, superadmin-only credential routes, checkout idempotency keys.
- Residual: no reconciliation job (gateway-vs-local drift undetected), no
  pending-expiry sweeper documented, refund-partial unsupported, receipt
  surface minimal. Race safety verified by tests; re-verify under K4 flood.

## 11. Security risks

- Audited: Policies on all IDOR-sensitive routes (attachment/photo/message/
  courtship/payment retry owner-scoped), mass assignment via validated/only,
  MIME-verified uploads with executable blocklist, unguessable storage paths,
  throttled auth/AI/chat/payment routes, signed email verification, 2FA OTP,
  private-photo + bio_visibility gates in API/Blade/avatar fallback.
- Residual: admin panel is high-value target (ensure IP allowlist + audit
  review in production); user content rendered escaped (keep it that way);
  PSEO/admin must never leak fraud scores or moderation internals (verified
  clean); penetration test recommended before launch.

## 12. Recommended architecture (incremental, no microservices)

```text
                ┌──────────────┐      ┌──────────────┐
                │  MySQL (app) │◄────►│ Redis        │
                │  + read      │      │ queue/cache/ │
                │  replica(s)  │      │ session/pub  │
                └──────────────┘      └──────┬───────┘
                                             │ Reverb ×N (sticky LB)
                                    ┌────────▼────────┐
                                    │ Laravel ×N      │
                                    │ (stateless)     │
                                    └─────────────────┘
  Later, only if measured: Meilisearch (search), time-partitioned
  messages/audit_logs, S3/CDN for media, error tracker + metrics.
```

Order: Redis (env-only) → indexes → retention → throttled writers →
warming/caches → Reverb horizontal → AI caps → search/partition if measured.

## 13. P0 tasks

- `config/queue.php`, `config/cache.php`, `config/session.php`, `DEPLOY.md`:
  Redis for queue/cache/session.
- New migration: notifications morph+time; matches per-side active;
  users geo/flags; audit_logs time.
- `app/Jobs/*`: `tries/timeout/backoff` + failed-job ops doc.
- `app/Services/AiService.php`: spend cap + kill switch.

## 14. P1 tasks

- Retention jobs + `routes/console.php` schedule (notifications, audit_logs,
  jobs, sessions, stale match_scores).
- Throttled `RecordProfileView` (listener+job; never sync-per-view).
- Register-time score warming (`MatchingEngine::scoreMany` reuse).
- `PublicSeoController`/`SeoService` response cache; analytics 60–300s cache.

## 15. P2 tasks (when measured)

- Scout + Meilisearch; Reverb multi-node; time partitioning;
  relaxed-radius backfill in `DiscoveryService.php`.

## 16. P3 tasks (polish) — done 2026-09-24

- Skeletons: `wire:loading` + `jk-skeleton` di discover-grid, match-list,
  chat-inbox, visitor-list (CSS `jk-skeleton`/`jk-spinner` sudah ada).
- Admin bulk: users `bulkAction` (sudah ada) + moderasi `bulk-decide`
  (≤100 item, transaksional, audit per item; kolom `reviewer_id` baru via
  migrasi `2026_09_24_000002`). Verifikasi approve/reject tetap satuan
  (butuh notes per item saat reject).
- Ops runbook: `docs/OPS-RUNBOOK.md`; CHANGELOG scale notes: Unreleased P0–P3.

## 17. Required load tests (before claiming readiness)

1. 200 concurrent mixed-filter discover, p95 < 800ms @100k users.
2. 100 msg/s sustained (broker + DB write path).
3. 500 signups/min (cold-start behavior).
4. Duplicate/out-of-order webhook flood (idempotency).
5. 5k concurrent Reverb connections, single node (ceiling).
6. Daily picks + GenerateDailyMatches @100k users (batch runtime).

---

## AUDIT COMPLETE

- **P0**: Redis for queue/cache/session · D-indexes migration (notifications/matches/users-geo-flags/audit time) · job tries-timeout-backoff · AI spend cap + kill switch.
- **P1**: retention/pruning jobs + schedule · throttled profile-view writer · register-time score warming · PSEO/analytics response cache.
- **P2**: Meilisearch (if measured) · Reverb multi-node · time partitioning · liquidity backfill.
- **P3**: skeletons (4 list Livewire) · admin bulk (users + moderation bulk-decide) · runbook · changelog notes.
