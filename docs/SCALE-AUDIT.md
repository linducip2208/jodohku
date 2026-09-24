# SCALE AUDIT — Jodohku (architecture audit, no code changed)

Date: 2026-09-24 · HEAD: `4630dfc` · Scope: million-user readiness.
Method: static inspection of migrations, models, services, jobs, routes,
config, docs + measured query counts on a 5,003-user MySQL dataset
(`perf.php`: discover p1 163ms/12q, filtered 151ms/10q, dailyPicks 384ms/32q,
inbox 12ms/5q, notifications 3ms/1q). No benchmarks fabricated; numbers above
are the only measured figures in this document.

## A. Current architecture diagram

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
  `SESSION_DRIVER=database`). Redis/queue config exists but is opt-in.
- All 14 notifications implement `ShouldQueue`; all 16 domain events implement
  `ShouldBroadcast` (Reverb, single node, no horizontal story).
- Matching: `MatchingEngine` (8 normalized dims, hard filter block/status) +
  `DiscoveryService` (filter → pool → in-memory score → boost only reorders) +
  `MatchScore` read-path (`scoreMany`, TTL recompute) + versioned weights.
- Chat: `ChatService` (idempotent `client_message_id`, per-peer quota,
  local-first moderation → `ModerationLog` row + optional AI review job).
- Payments: gateway manager, HMAC webhooks, idempotent fulfill w/ row locks.
- Taaruf: `CourtshipService` (row-locked advance, anti-self-approve guardian).
- SEO/PSEO: cached sitemap sections, quality-gated city pages, opt-in `/u/*`.

## B. Current bottlenecks (evidenced)

1. **Single MySQL does everything** (app + jobs + cache + sessions). At scale
   the jobs/cache/session tables contend with product tables on one instance.
2. **Discovery pool is full-row + in-memory scoring.** `discover()` fetches up
   to `perPage*4` users with 4 relations, scores in PHP. Fine at 5k
   (163ms/12q) but cost grows with pool size, not page size; deep pages
   re-walk the pool.
3. **`match_scores` grows per viewed pair** (upsert on every discovery miss).
   No TTL cleanup; unbounded growth (~n × viewed).
4. **Per-message sync writes**: `ModerationLog::create` on EVERY message +
   `messages` + `conversation.last_message_at` + broadcast, all inside the
   send path. Moderation queue only for flagged.
5. **`notifications` table (default Laravel) has ZERO indexes** and grows
   forever (no retention). Every inbox load scans notifiable morph columns.
6. **Admin analytics uncached**: `Admin\AnalyticsController` (12 methods) runs
   raw COUNTs per page load; dashboard snapshot IS cached 60s
   (`DashboardController`), analytics is not.
7. **Profile visitors are write-never**: `ProfileViewed` event fires but no
   writer persists `profile_views` in production (only demo seeder does) —
   dead read path; adding the write naively = 1 insert per profile view.
8. **AI cost is log-only**: per-user/min/day limits exist, but no spend cap
   enforcement or budget kill-switch in `AiService`.

## C. Critical scalability risks

- **C1. DB-is-everything.** Queue depth, cache stampedes, session writes share
  the product instance. First scaling wall.
- **C2. Reverb single node.** Presence + per-message broadcast have no
  multi-node story (needs Redis adapter + sticky/LB plan, unconfigured).
- **C3. Unbounded tables**: `notifications`, `audit_logs`, `jobs`,
  `sessions`, `match_scores`, `ai_usage_logs`, `profile_views` (once wired)
  have no pruning/retention/archival.
- **C4. Geo is a bounding box on unindexed float columns** — full scan shape
  at 1M users.
- **C5. Search is `LIKE %kw%` across users/profile** — full scans, no index
  infrastructure (no Scout/Meilisearch/TNTSearch in `composer.json`).
- **C6. Cold start**: new users get empty `match_scores`; first discovery
  computes live (ok per-user, but a registration wave hammers it; no
  precompute job on register).
- **C7. Liquidity**: matching quality collapses in small cities; no
  backfill/relaxation strategy beyond static daily picks.

## D. Missing indexes (justified by actual queries)

1. `notifications`: `(notifiable_type, notifiable_id, created_at)` +
   `(notifiable_type, notifiable_id, read_at)` — every inbox/notification
   count query. **Highest value.**
2. `users`: `(latitude, longitude)` for geo bounding boxes;
   `(is_online)`, `(is_verified)`, `(is_premium)`, `(is_demo, status)` for
   filters/counters used in discovery, PSEO counts, admin.
3. `matches`: `(user_a_id, is_active)` + `(user_b_id, is_active)` — the
   `WHERE (a=? OR b=?) AND is_active` pattern can't use the canonical unique
   index efficiently.
4. `audit_logs`: `(created_at)` for retention deletes (has actor/action
   indexes only).
5. `messages`: covered (`conversation_id, created_at`) ✓. `likes`,
   `match_scores`, `courtships`, `consultations`, `events`, `forums` covered ✓.

## E. Expensive queries (measured or by inspection)

- `discover()` pool + PHP scoring: 163ms/12q @5k (ok now, superlinear later).
- `dailyPicks` → 2× `candidatesFor`: 384ms/32q @5k (was 1013ms/1063q before
  memoization: `weights()` ×8 Setting reads/pair, `Setting` miss not cached,
  per-candidate block checks — all fixed this cycle, see H).
- Admin analytics COUNTs uncached (F).
- PSEO `cityCounts` cached 1h ✓; sitemap cached ✓; demographic 10m ✓.
- `User::active()->real()` without index on is_online/verified for filters.

## F. N+1 risks (audited)

- Eliminated this cycle: block checks in pool loops (`blockedIdsFor` memo),
  approved-photo counts (`withCount`), weights/settings memos.
- Remaining: `ChatWindow` loads messages +5 relations once (fine);
  `MatchList`/cards rely on caller eager loads (verified in controllers);
  ` путешествие `: `Courtship::with` in show ✓.
- Watch: `UserResource` lazy `profilePrivacy()->first()` when relation not
  loaded — callers eager-load, but any new caller reintroduces it.

## G. Missing cache opportunities

- `Event::open()` lists, forum index, membership plans/gifts catalogs:
  read-mostly, currently uncached (small tables — low priority).
- `dailyPicks` result itself (not just exclusion list): cacheable per
  user/day since picks are deterministic for the day.
- PSEO city pages: view-level `Cache-Control` present; add response cache.

## H. Missing async jobs

- Profile-view recording (see B7): needs throttled job (unique
  viewer→profile per day) — do NOT write synchronously per view.
- `ModerationLog` per-message write → buffer/async at volume.
- MatchScore warming on register (cold start C6).
- AI digest/catch-up: already fallback-safe; move to job + cache at volume.
- Broadcast fan-out (`BroadcastMessage` loops users synchronously in chunks):
  needs queued batch job.

## I. Missing search/index infrastructure

- No Scout/Meilisearch/TNTSearch. `LIKE %kw%` over users/profile/bio.
- Recommendation: Meilisearch (self-hostable, Laravel Scout) for
  users/posts/forums when users table passes ~100k; NOT now (overhead
  unjustified at current scale).

## J. Missing observability

- No slow-query log review process, no error tracker (Sentry/Flares) wired,
  no queue-depth alerting, no Reverb connection metrics.
- `/health` covers db/cache/storage/queue-depth + version, no secrets ✓.
- `failed_jobs` table exists (check migration) but no alerting/retry policy
  documented; jobs lack explicit `tries`/`timeout`/`backoff`.

## K. Required load tests (before claiming readiness)

1. `k6`/`artisan`-driven: 200 concurrent discover (mix filters) — target
   p95 < 800ms @100k users (seeded via `jodohku:demo --users=100000`).
2. Message throughput: 100 msg/s sustained — broker + DB write path.
3. Registration wave: 500 signups/min — cold-start scoring behavior.
4. Webhook flood: duplicate/out-of-order gateway callbacks — idempotency.
5. Broadcast: 5k concurrent Reverb connections single node — establish ceiling.
6. Daily picks + GenerateDailyMatches at 100k users — batch runtime.

## L. Recommended implementation order

1. Move queue/cache/session to Redis (env-only change + docs).
2. Add D1–D4 indexes + notifications index (migration, backward compatible).
3. Retention/pruning jobs (notifications read, audit_logs, jobs, sessions,
   match_scores stale) + scheduler entries.
4. Throttled profile-view writer job (fixes dead visitors path safely).
5. MatchScore warming on register + dailyPicks result cache.
6. Reverb horizontal plan (Redis adapter, LB sticky) + connection test (K5).
7. AI spend cap + kill switch in `AiService`.
8. Meilisearch only after K1 shows search pain.
9. Partitioning (`messages`, `audit_logs` by time) only after K-measurements
   justify it. No microservices: no demonstrated bottleneck.

---

## P0 (production blockers if traffic grows)

- `app/Providers/AppServiceProvider.php`, `config/queue.php`, `config/cache.php`,
  `config/session.php`, `DEPLOY.md`: move queue/cache/session to Redis.
- New migration: `notifications` morph+time indexes; `matches`
  `(user_a_id/is_active)+(user_b_id/is_active)`; `users` geo/flag indexes;
  `audit_logs(created_at)`.
- `app/Jobs/*` (all): add `tries/timeout/backoff`; document failed-job ops.
- `app/Services/AiService.php`: spend cap + kill switch (log-only today).

## P1 (quality at scale)

- Retention jobs: `app/Console/Commands/*:prune` or jobs for notifications,
  audit_logs, jobs table, sessions, stale match_scores + `routes/console.php`
  schedule entries.
- `app/Listeners/*` (new `RecordProfileView` throttled) + `app/Jobs/*`.
- `GenerateDailyMatches` + register-time warming (`app/Listeners`,
  `app/Services/MatchingEngine.php::scoreMany` reuse).
- `PublicSeoController`, `SeoService`: response cache for PSEO pages.
- `Admin\AnalyticsController.php`: 60–300s cache like dashboard.

## P2 (when measured)

- Scout + Meilisearch (`composer.json`, searchable models, commands).
- Reverb multi-node (`config/reverb.php`, `config/broadcasting.php`, DEPLOY.md).
- `database/migrations/*_partition_*`: time-partition messages/audit_logs.
- Cold-start + liquidity: relaxed-radius backfill in `DiscoveryService.php`.

## P3 (polish)

- `resources/views/*` skeleton coverage gaps; admin bulk actions;
  `TROUBLESHOOTING.md` ops runbook (queue/Reverb/Redis); `CHANGELOG.md` scale notes.
