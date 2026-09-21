# Jodohku — Biro Jodoh Modern Indonesia

Platform matchmaking + chat aman + verifikasi + virtual member transparan, dibangun di atas Laravel 13.

## Fitur

- **Discovery & matchmaking**: skor kompatibilitas 8 dimensi (`MatchingEngine`), hard filter blokir/status, daily picks, boost hanya memengaruhi urutan tampil.
- **Like / Super-like / Favorite / Rewind**: mutual-like otomatis membuat `matches` kanonis idempoten (`LikeService`, `Like::createsMatch`). Free dibatasi harian (`FREE_DAILY_LIKES`, default 20), Premium unlimited; Who Liked You & Visitors khusus Premium (403 + flag upgrade untuk free).
- **Chat realtime**: direct conversation, idempotensi `client_message_id`, read receipt, reaksi, typing broadcast via Reverb, notifikasi DB+mail.
- **Moderasi lokal-first**: `ProfanityService` + `ScamDetectionService` → `MessageModerationService` (allow/mask/warning/flag/block), AI review hanya saat flagged (`ProcessMessageModeration`).
- **Virtual member transparan**: akun `virtual`/`ai` selalu berlabel, mode template/hybrid/ai, trigger `user.registered` dengan cap harian + jam aktif 08–22 + cooldown (`VirtualMemberService`), takeover operator menjeda AI (`ai_paused_at`).
- **Monetisasi**: subscription Free/Premium/VIP (`MembershipService`/`SubscriptionService`), kredit wallet tanpa-minus (`CreditService`), gift, 4 gateway Indonesia (iPaymu/Xendit/Midtrans/Tripay) dengan webhook idempoten.
- **Verifikasi & fraud**: request dokumen, risk score, event log, scam detector (no HP, invite off-platform, phishing, investment lure).
- **Foto aman**: pipeline `PhotoService` (validasi MIME asli + dimensi, resize 1600 + thumbnail 400, deteksi duplikat SHA-256, status pending → antrian moderasi admin), foto pending tak terlihat publik.
- **2FA email OTP**: `TwoFactorService` (kode 6 digit hash + TTL 10 mnt + throttle), wajib di login web & API bila aktif.
- **SEO publik**: `/sitemap.xml` (blog + forum saja), `robots.txt` menutup area member/admin/API.
- **Admin/operator**: RBAC zona (`staff/moderator/operator/admin/superadmin` + policies, sidebar `@can`, secret gateway hanya superadmin), audit log tanpa-sekret, gateway secrets terenkripsi (`Crypt`).
- **E2E teruji**: `EndToEndJourneyTest` (register → match → chat idempoten → webhook premium/kredit → block/report → moderasi + audit), `RbacTest`, `ThrottleIsolationTest`.

## Stack

Laravel 13 · PHP 8.3 · Sanctum · Reverb · Queue database/redis · SQLite (dev/test) / MySQL (prod) · S3-compatible storage · Vite + Tailwind · Livewire.

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
php artisan test
```

## Dokumentasi

- `INSTALL.md` — instalasi lokal
- `DEPLOY.md` — queue, scheduler, Reverb, SSL
- `ENVIRONMENT.md` — tabel env
- `DATABASE.md` — grup tabel & constraint
- `ARCHITECTURE.md` — services & keputusan
- `API.md` — endpoint `/api/v1`
- `PAYMENT.md` — gateway & webhook
- `AI.md` — provider & guardrail
- `CHAT.md` — alur chat & moderasi
- `MATCHMAKING.md` — pipeline & bobot
- `MODERATION.md` — kebijakan moderasi
- `VIRTUAL-MEMBER.md` — transparansi virtual/AI

Seed persons bersifat **fiksi** (`*.example.test`) khusus untuk development.
