# Jodohku — Biro Jodoh Modern Indonesia

**Bahasa:** [Indonesia](README.md) · [English](README.en.md) · [العربية](README.ar.md)

Platform matchmaking premium + chat aman + taaruf terpandu + komunitas, dibangun di atas Laravel 13. Tinder-like discovery, Skadate-like matchmaking, biro jodoh Indonesia, AI matchmaker, dan whitelabel multi-brand — dalam satu produk koheren.

## Fitur

### Discovery & Matchmaking
- **Discovery & matchmaking**: skor kompatibilitas 8 dimensi (`MatchingEngine`), hard filter blokir/status, daily picks, boost hanya memengaruhi urutan tampil.
- **Like / Super-like / Favorite / Rewind**: mutual-like otomatis membuat `matches` kanonis idempoten (`LikeService`, `Like::createsMatch`). Free dibatasi harian (`FREE_DAILY_LIKES`, default 20), Premium unlimited; Who Liked You & Visitors khusus Premium (403 + flag upgrade untuk free).
- **Saved filters**: simpan, rename, duplikat, default, hapus (maks 10) — web + API `/api/v1/saved-filters`, siap Flutter.
- **Passport / travel mode**: lokasi virtual Premium, akustik discovery, audit log; koordinat exact tak pernah diekspos (jarak di-fuzz 0.5 km).
- **Penjelasan match**: skor + alasan "kenapa cocok" (`MatchExplanation`, komponen `why-match`, API explain).

### Profil, Foto & Video
- **Profil polished**: foto galeri, foto sampul, prompt Q&A, kuesioner, skor kelengkapan, status online/terakhir aktif, badge verified/premium/boosted.
- **Foto aman**: pipeline `PhotoService` (MIME asli + dimensi, resize 1600 + thumbnail 400, duplikat SHA-256, pending → moderasi), foto pending/privat tak terlihat publik; fallback avatar tak pernah bocorkan foto privat.
- **Video profil**: MP4/MOV ≤50MB, maks 3/user, tolak polyglot, pending moderasi admin (`/admin/moderation/videos`), batas durasi opsional via `FFPROBE_PATH`.
- **Verifikasi bertingkat**: badge email/telepon/foto/identitas (dari request approved), upload dokumen ke private disk, review admin, audit log.

### Keselamatan
- **Safety Center**: lapor user/pesan/profil/foto, blokir, unmatch, mute, restrict, riwayat laporan — terjangkau dari profil, match, chat, call.
- **Contact blocking**: privacy-preserving (hash HMAC, nomor mentah tak disimpan), terintegrasi ke discovery.
- **Moderasi lokal-first**: `ProfanityService` + `ScamDetectionService` → `MessageModerationService` (allow/mask/warning/flag/block), AI review hanya saat flagged; antrean admin + bulk + appeal.
- **Fraud/scam**: risk score (IP/velocity/device), pola romance-scam, ambang review manusia — tak pernah auto-ban dari sinyal AI lemah.

### Chat, Call & Notifikasi
- **Chat realtime**: direct conversation, idempotensi `client_message_id`, read receipt, reaksi, reply, lampiran (unduh via API ber-policy), typing broadcast via Reverb, draf tersimpan per-percakapan (localStorage outbox), banner offline.
- **Voice/video call**: lifecycle + billing idempoten per-menit (`CallService`), WebRTC (`call.js`: STUN/TURN, ICE, mute/kamera/reconnect/timer), riwayat call; missed/rejected tak pernah ditagih.
- **Notifikasi**: like, super-like, match, pesan, mention, gift, pembayaran, verifikasi, taaruf, call — via database + realtime + email + push; preferensi granular; deep links.
- **Push**: abstraksi provider (default `log` auditabel; FCM HTTP v1 OAuth2 + fallback legacy), device token multi-perangkat, invalid token auto-disable.

### Monetisasi & Pertumbuhan
- **Premium**: Free/Premium/VIP (`MembershipService`/`SubscriptionService`), entitlement ditegakkan di backend, tak pernah hanya di frontend.
- **Boost**: beli 50 kredit, countdown, efek ranking, riwayat, expiry scheduler, anti-abuse (spend ber-reference idempoten).
- **Gifts**: katalog, kirim/terima, riwayat, notifikasi, anti-fraud.
- **Referral & afiliasi**: kode/link, tracking, reward idempoten, dashboard + tombol share, komisi afiliasi + approval admin.
- **Payment**: 4 gateway Indonesia (iPaymu/Xendit/Midtrans/Tripay), webhook HMAC idempoten (`event_id`), refund gateway-dulu + clawback tanpa-minus.

### AI, Taaruf & Konselor
- **AI matchmaker**: hanya merekomendasikan user nyata dari `DiscoveryService` (grounded, tak pernah mengarang), ekstraksi filter bahasa alami + penjelasan.
- **AI assistant**: bio, prompt, icebreaker, saran balasan, ide kencan — tak pernah kirim otomatis; rate-limit + budget cap + usage log.
- **Taaruf lifecycle**: match → request → accept → values/agama/keluarga → konselor → selesai, dengan guardian/chaperone, stage rules, reminders, audit.
- **Konselor marketplace**: profil, rating, booking anti-overlap, pembayaran, sesi video/chat, earnings, komisi admin.
- **Virtual member transparan**: akun `virtual`/`ai` selalu berlabel, mode template/hybrid/ai, trigger + cap harian + jam aktif, takeover operator menjeda AI.

### Komunitas & Events
- **Social graph**: follow/unfollow/mute + suggested (`FollowService`), privasi granular.
- **Feed**: `FeedService` (candidates → filter → skor → rank → paginate), trending, boost postingan 24 jam, bookmark/share/reaksi 6 tipe/hashtag/mention.
- **Stories 24 jam**, **Groups** (roles + cover + invite/request flow + halaman publik `/g/{slug}`), **forums/blog**, **global search** privacy-aware.
- **Events**: meetup online/offline, RSVP → saran kenalan peserta (`/events/{event}/kenalan` + API suggested), nearby geo-search.

### Admin, Analitik & Keamanan
- **Admin control center**: dashboard KPI, funnel penuh (register → verified → discover → like → match → chat → call → taaruf → paying), cohort analysis, audit log tanpa-sekret, gateway secrets terenkripsi.
- **Privacy Center**: visibilitas, incognito, blokir/bisu, contact blocking, ekspor data, jeda akun, hapus akun permanen (anonimasi PII + revoke token/sesi — GDPR-style), manajemen sesi.
- **Keamanan**: 2FA email-OTP + TOTP authenticator (RFC 6238, QR, backup codes sekali-pakai), OTP telepon HMAC + rate-limit, throttle login/register, policies terdaftar penuh (termasuk Courtship/Consultation/Brand), anti-IDOR, upload tervalidasi.
- **SEO/PSEO**: sitemap dinamis, robots, 14 kota + 5 topik dengan quality gate, schema JSON-LD, profil publik opt-in.

### Mobile, PWA & API
- **Landing premium**: hero, cara kerja, online-now, demo members, feed preview, membership, FAQ, CTA — mobile-first.
- **PWA**: manifest brand-aware, service worker + offline fallback, ikon, install prompt.
- **API Flutter-ready**: `/api/v1/` konsisten (auth, discover, chat, call, profil, sosial, events, pembayaran, push, filter) + stub OpenAPI (`docs/API-V1-OPENAPI.yaml`).
- **Demo komersial**: `php artisan jodohku:demo --users=5000` (deterministik, bulk, foto synthetic aman) — lihat `DEMO.md`.

### Whitelabel Tier 3 (multi-brand)
- Tabel `brands`: nama, tagline, warna, logo, favicon, domain, feature flags — resolusi per-domain (cache 1 jam), fallback Jodohku.
- Wizard onboarding 4 langkah (live preview + preview landing), admin CRUD, export/import paket zip.
- Brand di layout/mail/manifest/PWA + ikon install; flag ditegakkan backend (`brand.feature` → 404).
- Role `client`: kelola hanya brand sendiri (`BrandPolicy`); `users.brand_id` atribusi registrasi. Lihat `WHITELABEL.md`.

## Stack

Laravel 13 · PHP 8.3 · Sanctum · Reverb · Queue database/redis · SQLite (dev/test) / MySQL (prod) · S3-compatible storage · Vite + Tailwind · Livewire · Alpine.js (bundled).

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

## Dokumentasi

- `INSTALL.md` — instalasi lokal
- `DEPLOY.md` — queue, scheduler, Reverb, SSL
- `ENVIRONMENT.md` — tabel env
- `DATABASE.md` — grup tabel & constraint
- `ARCHITECTURE.md` — services & keputusan
- `API.md` + `docs/API-V1-OPENAPI.yaml` — endpoint `/api/v1`
- `PAYMENT.md` — gateway & webhook
- `AI.md` — provider & guardrail
- `CHAT.md` — alur chat & moderasi
- `MATCHMAKING.md` — pipeline & bobot
- `MODERATION.md` — kebijakan moderasi
- `VIRTUAL-MEMBER.md` — transparansi virtual/AI
- `SMART-TAARUF.md` — lifecycle taaruf & konselor
- `DEMO.md` — dataset demo 5000 user, foto, reset
- `SEO.md` / `GEO.md` / `PSEO.md` — arsitektur SEO + PSEO kota/panduan
- `SECURITY.md` / `PRIVACY.md` — model ancaman & matriks privasi
- `ADMIN.md` — kontrol admin
- `WHITELABEL.md` — multi-brand Tier 3
- `TROUBLESHOOTING.md` — pemecahan masalah
- `CHANGELOG.md` — versi & riwayat rilis

Seed persons bersifat **fiksi** (`*.example.test`) khusus untuk development.
