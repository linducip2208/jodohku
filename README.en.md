# Jodohku — Modern Indonesian Matchmaking

**Languages:** [Indonesia](README.md) · [English](README.en.md) · [العربية](README.ar.md)

A premium matchmaking platform + safe chat + guided taaruf + community, built on Laravel 13. Tinder-like discovery, Skadate-like matchmaking, Indonesian biro jodoh, AI matchmaker, and multi-brand whitelabel — one coherent product.

## Features

### Discovery & Matchmaking
- **Discovery & matchmaking**: 8-dimension compatibility scoring (`MatchingEngine`), block/status hard filters, daily picks; boosts only affect ranking order.
- **Like / Super-like / Favorite / Rewind**: mutual likes auto-create canonical idempotent `matches` (`LikeService`, `Like::createsMatch`). Free tier has daily limits (`FREE_DAILY_LIKES`, default 20), Premium unlimited; Who Liked You & Visitors are Premium-only (403 + upgrade flag for free).
- **Saved filters**: save, rename, duplicate, set default, delete (max 10) — web + `/api/v1/saved-filters` API, Flutter-ready.
- **Passport / travel mode**: Premium virtual location with audit logging; exact coordinates are never exposed (distances fuzzed to 0.5 km).
- **Match explanations**: score + "why you match" reasons (`MatchExplanation`, `why-match` component, explain API).

### Profile, Photos & Video
- **Polished profiles**: photo gallery, cover photo, Q&A prompts, questionnaire, completeness score, online/last-active status, verified/premium/boosted badges.
- **Safe photos**: `PhotoService` pipeline (real MIME + dimensions, 1600px resize + 400px thumbnails, SHA-256 duplicate detection, pending → admin moderation); pending/private photos never public; avatar fallback never leaks private photos.
- **Profile video**: MP4/MOV ≤50MB, max 3/user, polyglot rejection, pending admin moderation (`/admin/moderation/videos`), optional duration cap via `FFPROBE_PATH`.
- **Tiered verification**: email/phone/photo/identity badges (from approved requests), private-disk document uploads, admin review, audit log.

### Safety
- **Safety Center**: report user/message/profile/photo, block, unmatch, mute, restrict, report history — reachable from profile, match, chat, and calls.
- **Contact blocking**: privacy-preserving (HMAC hashes, raw numbers never stored), integrated into discovery.
- **Local-first moderation**: `ProfanityService` + `ScamDetectionService` → `MessageModerationService` (allow/mask/warning/flag/block); AI review only when flagged; admin queue + bulk + appeals.
- **Fraud/scam**: risk scoring (IP/velocity/device), romance-scam patterns, human-review thresholds — never auto-ban on weak AI signals.

### Chat, Calls & Notifications
- **Realtime chat**: direct conversations, `client_message_id` idempotency, read receipts, reactions, replies, attachments (policy-gated API download), Reverb typing broadcasts, per-conversation drafts (localStorage outbox), offline banner.
- **Voice/video calls**: lifecycle + idempotent per-minute billing (`CallService`), WebRTC (`call.js`: STUN/TURN, ICE, mute/camera/reconnect/timer), call history; missed/rejected calls are never charged.
- **Notifications**: likes, super-likes, matches, messages, mentions, gifts, payments, verification, taaruf, calls — database + realtime + email + push; granular preferences; deep links.
- **Push**: provider abstraction (auditable `log` default; FCM HTTP v1 OAuth2 + legacy fallback), multi-device tokens, invalid tokens auto-disabled.

### Monetization & Growth
- **Premium**: Free/Premium/VIP (`MembershipService`/`SubscriptionService`), entitlements enforced backend-side, never frontend-only.
- **Boost**: 50-credit purchase, countdown, ranking effect, history, scheduler expiry, abuse prevention (idempotent reference-tagged spend).
- **Gifts**: catalog, send/receive, history, notifications, fraud protection.
- **Referral & affiliate**: codes/links, tracking, idempotent rewards, dashboard + share buttons, affiliate commissions + admin approval.
- **Payments**: 4 Indonesian gateways (iPaymu/Xendit/Midtrans/Tripay), idempotent HMAC webhooks (`event_id`), gateway-first refunds + non-negative clawback.

### AI, Taaruf & Counselors
- **AI matchmaker**: recommends only real users from `DiscoveryService` (grounded, never invents), natural-language filter extraction + explanations.
- **AI assistant**: bios, prompts, icebreakers, reply suggestions, date ideas — never sends automatically; rate limits + budget caps + usage logs.
- **Taaruf lifecycle**: match → request → accept → values/religion/family → counselor → completion, with guardians/chaperones, stage rules, reminders, audit.
- **Counselor marketplace**: profiles, ratings, overlap-safe booking, payments, video/chat sessions, earnings, admin commissions.
- **Transparent virtual members**: `virtual`/`ai` accounts always labeled, template/hybrid/ai modes, triggers + daily caps + active hours, operator takeover pauses AI.

### Community & Events
- **Social graph**: follow/unfollow/mute + suggested people (`FollowService`), granular privacy.
- **Feed**: `FeedService` (candidates → filter → score → rank → paginate), trending, 24h credit-boosted posts, bookmarks/shares/6 reaction types/hashtags/mentions.
- **24h stories**, **Groups** (roles + covers + invite/request flow + public `/g/{slug}` pages), **forums/blog**, privacy-aware **global search**.
- **Events**: online/offline meetups, RSVP → attendee discovery (`/events/{event}/kenalan` + suggested API), nearby geo-search.

### Admin, Analytics & Security
- **Admin control center**: KPI dashboard, full funnel (register → verified → discover → like → match → chat → call → taaruf → paying), cohort analysis, secret-free audit log, encrypted gateway secrets.
- **Privacy Center**: visibility, incognito, blocks/mutes, contact blocking, data export, pause, permanent GDPR-style deletion (PII anonymization + token/session revocation), session management.
- **Security**: email-OTP + TOTP authenticator 2FA (RFC 6238, QR, single-use backup codes), HMAC phone OTP + rate limits, login/register throttling, fully registered policies (incl. Courtship/Consultation/Brand), anti-IDOR, validated uploads.
- **SEO/PSEO**: dynamic sitemap, robots, 14 cities + 5 topics with quality gates, JSON-LD schemas, opt-in public profiles.

### Mobile, PWA & API
- **Premium landing**: hero, how-it-works, online-now, demo members, feed preview, membership, FAQ, CTA — mobile-first.
- **PWA**: brand-aware manifest, service worker + offline fallback, icons, install prompt.
- **Flutter-ready API**: consistent `/api/v1/` (auth, discover, chat, calls, profile, social, events, payments, push, filters) + OpenAPI stub (`docs/API-V1-OPENAPI.yaml`).
- **Commercial demo**: `php artisan jodohku:demo --users=5000` (deterministic, bulk, safe synthetic photos) — see `DEMO.md`.

### Whitelabel Tier 3 (multi-brand)
- `brands` table: name, tagline, colors, logo, favicon, domain, feature flags — per-domain resolution (1h cache), Jodohku fallback.
- 4-step onboarding wizard (live preview + landing preview), admin CRUD, zip package export/import.
- Brand-aware layouts/mail/manifest/PWA + install icons; flags enforced backend (`brand.feature` → 404).
- `client` role: manage only their own brand (`BrandPolicy`); `users.brand_id` registration attribution. See `WHITELABEL.md`.

## Stack

Laravel 13 · PHP 8.3 · Sanctum · Reverb · database/redis queues · SQLite (dev/test) / MySQL (prod) · S3-compatible storage · Vite + Tailwind · Livewire · Alpine.js (bundled).

## Quickstart

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run dev
php artisan serve
php artisan reverb:start
php artisan queue:work
```

Test & fresh seed:

```bash
php artisan migrate:fresh --seed --force
php artisan test   # 280 passed
```

## Docs

- `INSTALL.md` — local installation
- `DEPLOY.md` — queues, scheduler, Reverb, SSL
- `ENVIRONMENT.md` — env table
- `DATABASE.md` — table groups & constraints
- `ARCHITECTURE.md` — services & decisions
- `API.md` + `docs/API-V1-OPENAPI.yaml` — `/api/v1` endpoints
- `PAYMENT.md` — gateways & webhooks
- `AI.md` — providers & guardrails
- `CHAT.md` — chat flow & moderation
- `MATCHMAKING.md` — pipeline & weights
- `MODERATION.md` — moderation policy
- `VIRTUAL-MEMBER.md` — virtual/AI transparency
- `SMART-TAARUF.md` — taaruf lifecycle & counselors
- `DEMO.md` — 5000-user demo dataset, photos, reset
- `SEO.md` / `GEO.md` / `PSEO.md` — SEO architecture + city/guide PSEO
- `SECURITY.md` / `PRIVACY.md` — threat model & privacy matrix
- `ADMIN.md` — admin controls
- `WHITELABEL.md` — multi-brand Tier 3
- `TROUBLESHOOTING.md` — troubleshooting
- `CHANGELOG.md` — versions & release history

Seed persons are **fictional** (`*.example.test`) for development only.
