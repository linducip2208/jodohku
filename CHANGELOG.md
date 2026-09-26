# CHANGELOG — Jodohku

Format: `Added / Changed / Fixed / Security`. Version di `config/app.php`
(`APP_VERSION`).

## [Unreleased] — dating frontend polish

### Added
- Mode Swipe di Discover (`/discover?tab=orang&mode=swipe`, Livewire
  `swipe-deck`): gesture sentuh + tombol + panah keyboard, carousel foto,
  jarak/status/verified, rewind, skeleton/empty state, modal match.
- Filter tersimpan bernama (maks 10, terapkan via query, hapus) +
  filter pekerjaan/agama/tujuan + minat di retrieval.
- Onboarding 4 langkah (`/onboarding`: dasar → tujuan → foto →
  preferensi) + banner home bila profil tipis.
- Kirim icebreaker dari kartu match (`POST /matches/{user}/icebreaker-send`,
  `GET /ai/icebreakers/{user}`) — percakapan dibuat on-demand.
- Prompt profil (6 pertanyaan, tersimpan JSON) + tampil di tab Tentang.
- Tombol rewind web (`POST /rewind`, throttle) di halaman likes —
  backend `LikeService::rewind` akhirnya punya UI.
- Modal selebrasi match di `like-buttons` (like & superlike): CTA real
  ke `/matches` + profil pasangan. Terverifikasi browser end-to-end.

### Fixed
- Paket Premium 1 kolom di desktop → grid responsif `.jk-grid`.
- Composer chat inline `background:#fff` → token dark-mode.
- Tulis `docs/FRONTEND-AUDIT.md` (inventaris + temuan + non-temuan).

## [Unreleased] — premium matchmaking platform

### Added
- Passport/travel mode (Premium, virtual location in discovery, audit).
- Contact blocking privacy-preserving (hash HMAC, tanpa nomor mentah).
- Push architecture (log default, FCM HTTP v1 + legacy fallback, device tokens, deep links).
- Referral (kode/link, reward kredit idempoten) + afiliasi (komisi, approval) + tombol salin/share WA/X.
- WebRTC media layer (`call.js`: STUN/TURN, signaling via Reverb whispers,
  timer/reconnect/mute/kamera, billing tetap server-side idempoten).
- Profile cover + group cover + group invite/request flow + member event create.
- Privacy Center (blokir/bisu/sesi/ekspor/jeda) + story balas-via-chat.
- PWA (manifest, service worker, icons, install prompt, `/offline-fallback`).
- Admin afiliasi + `/admin/analytics/social`; SEO sitemap grup + robots.
- Saved filters: rename/duplikat/set-default (`is_default`, 4 endpoint baru).
- Landing: render `online-now`, `demo-members`, `feed-preview` yang sebelumnya mati.
- Boosted badge (⚡ BOOST) di kartu profil + status boost kaya.
- Verification upload web (`files[]` → private disk, max 3×10MB) — sebelumnya form tanpa file.
- Event → match: endpoint `suggested` (web `/events/{event}/kenalan` + API `/api/v1/events/{event}/suggested`, filter blok/paused) + RSVP bawa `suggested_count`.
- API saved-filters CRUD penuh (`/api/v1/saved-filters`, rename/duplikat/default) untuk Flutter.
- `UserResource` kini sertakan `verification` tiers (email/phone/photo/identity dari request approved).
- Delete-account GDPR: anonimasi PII + revoke token/sesi + wipe contact-hash + disable push.
- 2FA authenticator (TOTP RFC 6238 tanpa dep baru): setup secret + otpauth URL, backup codes sekali-pakai; email-OTP tetap fallback.
- Video profil: perbaiki kolom salah (`file_path` → `path`), MIME+ekstensi, tolak polyglot, maks 3/user, pending moderasi + audit; routes web+API + UI upload.
- Admin funnel penuh (verified → discover → like → match → chat → call → taaruf → paying); OpenAPI v1 stub di `docs/API-V1-OPENAPI.yaml`.
- Match modal confetti + pop (hormati reduced-motion); banner offline di chat.
- Settings: UI hubungkan authenticator (secret + otpauth URL + salin) + backup codes.
- Admin moderasi video (`/admin/moderation/videos`, approve/reject audit-logged).
- Alpine.js kini bundled via Vite (`alpinejs` npm); CDN unpkg hanya fallback bila build absen.
- Chat kirim dicegah saat offline (toast); `.env.example` tandai MAIL_LOG bahaya di prod.
- TOTP QR SVG (`/settings/2fa/totp/qr`, bacon-qr-code) — pindai tanpa ketik.
- Video: batas durasi opsional via `FFPROBE_PATH` (skip aman bila absen).
- Docs: contoh register API lengkap, INSTALL hitungan tes, SECURITY (TOTP/HMAC/GPS/FCM), DEPLOY (FCMv1/QR/ffprobe).
- Tutup gap MD-vs-source: API admin gateways, report resolve, attachment download, `/api/v1/health` alias; OpenAPI diperluas.
- Whitelabel Tier 3: tabel `brands`, resolusi per-domain (cache 1 jam), admin CRUD + wizard onboarding (live preview + preview landing), feature flag per-brand di navigasi, brand di layout/mail/manifest/PWA, export/import paket zip.
- Whitelabel P1: enforce `brand.feature` backend (web+API, 404), `users.brand_id` atribusi registrasi, role `client` + `BrandPolicy` scope own-brand.
- Whitelabel jualan: stats per brand (API+web, dasar tagihan), 5 template preset, ikon PWA auto-generate, copy landing per brand, lisensi `expires_at`/`max_users`.
- Whitelabel trust: verifikasi domain (DNS TXT / HTTP well-known, reset saat ganti domain, mode ketat opsional), enforce kuota `max_users` di registrasi.
- Whitelabel enforce lengkap: flag gifts/boost di service + sender email per brand + docs subdomain wildcard.
- Chat draft outbox: draf tersimpan per-percakapan (localStorage), pulih saat kembali.
- Sapu TODO: tidak ada TODO/FIXME/placeholder di app/views; 623 routes OK.

### Security
- Throttle `web-login` (5,1) + `web-register` (10,1); `phone-verify` wajib auth + throttle.- OTP telepon disimpan sebagai HMAC-SHA256 (bukan plaintext) + rate-limit per-IP & per-nomor + verify 5/5mnt.
- 2FA API (`/api/v1/auth/2fa/verify`) rate-limit per-user+IP 5/5mnt.
- Daftarkan `Courtship/Consultation/CompatibilityReport` policies yang sebelumnya mati.
- `GroupPolicy` MembersOnly kini wajib anggota/pengelola (tutup kebocoran view).
- Jarak discovery di-fuzz ke kelipatan 0.5km (anti-triangulasi GPS exact).
- `avatarUrl()` fallback kini filter `approved + !private` juga saat relasi preloaded.
- Verification submit web di-throttle (5,1) + validasi MIME.

### Fixed
- `CallService::end()` idempoten: row-lock + replay aman + guard transaksi `reference_type=call`.
- `BoostService::activate()` serialize per-user; spend boost bawa `reference_type=boost`.
- `SettingsController::destroy` via API: guard-aware logout + session guard (perbaiki 500 `Session store not set` / `RequestGuard::logout`).
- FCM legacy ditandai deprecated; jalur utama FCM v1 OAuth2 service-account.
- Scheduler semua job `withoutOverlapping()->onOneServer()` (anti double-run).
- Index `users(latitude, longitude)` untuk discovery bounding-box.
- Duplikat route passport/kontak/referral/privasi dibersihkan; `admin.php` import rapi.
- Migrasi duplikat `000004` → `000005_user_phone_hash`.

## [Unreleased] — social dating platform

### Added
- Social graph: follow/unfollow/mute + suggested people (`FollowService`,
  tabel `follows`/`mutes`), daftar pengikut/mengikuti dengan privasi granular.
- Reaksi 6 tipe ekstensibel tanpa migrasi (`post/comment/story_reactions`,
  `ReactionService` + bulk `prime()` anti-N+1).
- Komentar balasan + edit/hapus + reaksi + laporkan; bookmark/simpanan;
  share dengan kutipan + counter; boost postingan kredit 24 jam.
- Composer community/group: upload foto (maks 4, JPG/PNG/WebP 8MB,
  MIME terverifikasi, `post-media/`, ikut visibilitas post) —
  terverifikasi end-to-end via browser.
- Hashtag (`HashtagService`, trending cache) + mention `@username`
  (`MentionService` + notifikasi) via `PostObserver`.
- Stories 24 jam (teks/foto/video, viewers, reaksi, expiry scope tanpa
  cron, prune +7 hari, optimasi media queue `ProcessMediaUpload`).
- Home feed `FeedService` (candidates→filter→skor→rank→paginate) +
  trending; Discover tabs (Orang/Postingan/Komunitas/Event/Trending).
- `SocialDatingRecommendationService` (skor sosial terpisah, alasan aman).
- `SearchService` + halaman `/cari` + API (orang/post/grup/event/tagar).
- Communities: halaman grup, join/leave, posting grup, kelola peran,
  halaman publik `/g/{slug}` + sitemap + robots.
- Event RSVP going/maybe/declined + redirect ramah web.
- Notifikasi social (follow/react/comment/mention/story) + preferensi
  granular + realtime `users.{id}` + bell Livewire realtime.
- Analytics privat via queue (`analytics_events`, tanpa IP/body/lokasi).
- Admin `/admin/analytics/social`; privasi profil granular
  (followers/following/posts/stories); pengaturan notifikasi baru.
- API `/api/v1/social/*` (follow, feed, stories, react, search, groups,
  recommendations). Seeder demo graph lengkap + reset cleanup.
- Dokumen: `SOCIAL_DATING_PLATFORM`, `SOCIAL_ARCHITECTURE`,
  `FEED_ARCHITECTURE`, `RECOMMENDATION_ARCHITECTURE`, `MEDIA_PIPELINE`,
  `SCALE_PLAN`; `MODERATION.md` + README diperbarui.

### Fixed
- Premium gate JSON mentah (`/visitors`, `/who-liked`) → redirect
  `/premium` + pesan upgrade untuk navigasi web.
- RSVP event JSON mentah → redirect ramah web.

## [Unreleased] — social-first visual regression fix

### Fixed
- Landing tanpa style: `member.css` tidak dimuat di layout landing sehingga
  komponen `jk-*` (avatar strip, skeleton) tampil polos — sekarang dimuat.
- Konselor tampil sebagai prospek dating: `User::counselor()` + pengecualian
  di pool retrieval kanonisik, `PersonalizationService`, dan semua query
  showcase landing (dedup antar-seksi via `user_id`).
- Pratinjau feed publik: penulis incognito dikecualikan.
- Hero memakai nama hardcode (Ayu/Rizky) — diganti data demo nyata.
- Duplikat section "Orang untukmu" + id ganda di `/home`.
- Premium gate (`/visitors`, `/who-liked`) mengembalikan JSON mentah saat
  navigasi web — dialihkan ke `/premium` dengan pesan upgrade.
- Feed: foto postingan (`media_paths`) kini dirender di `social-post`.
- Hero mobile: strip 3 kolom sempit → horizontal snap-scroll.

## [Unreleased] — compact landing community preview

### Fixed
- Pratinjau komunitas vertikal 6 postingan → maks 3 kartu kompak
  (grid 3 kolom desktop, carousel snap-scroll mobile, tanpa clipping).
- Query preview `limit(6)` → `limit(4)`: 1 post hero + 3 kartu, hero
  tidak tampil ganda di preview (dedup via `skip(1)`).
- Kartu hanya avatar/nama/verified/waktu/body 100 char/likes/komentar —
  tanpa form, komentar, atau kontrol moderasi; CTA keluar ke `/register`.
- Header "Lihat semua →": member → `/komunitas`, tamu → `/register`
  (`/komunitas` butuh auth; tanpa route baru).

### Added
- `CommunityPreviewTest`: maks 3 kartu, tanpa form, truncasi + dedup hero,
  tautan tamu/member.

### Added
- `OnlineMemberRegressionTest`: eksklusi konselor, dedup strip/grid,
  CTA-scope tamu, privasi pratinjau, pool retrieval, premium redirect.

## [Unreleased] — scale hardening P0–P3

### Added
- Discovery: `CandidateRetrievalService` (satu pool kanonisik untuk
  `DiscoveryService` + `MatchingEngine`), indeks skala
  (`notifications` morph+time, `matches` per-side active, `users` geo/flags,
  `audit_logs` time), backfill radius 2x/3x untuk kota tipis.
- Queue: `HasScaleLimits` di 20 jobs (`tries/timeout/backoff`; payment 5x,
  batch harian 2x/30 mnt), `failed()` logging + runbook di `DEPLOY.md`.
- AI: monthly spend cap (`AI_MONTHLY_CAP_USD`) + kill switch
  (`AI_KILL_SWITCH`) enforced sebelum provider call.
- Retention: `PruneStaleData` harian (notif read 90d/semua 365d, audit 180d,
  failed 30d, AI log 365d, profile views 180d, match_scores stale 90d,
  jobs stuck 7d) + `CleanupOldSessions` tetap 03:00.
- Profile views: `ProfileViewed` → `RecordProfileView` async, max 1/hari/
  pasangan, hormati `allow_profile_views`, tanpa tulis sinkron.
- Warming: `UserRegistered` → `RecalculateMatches` async (cold start).
- Cache: analytics admin 60–300s per endpoint, sitemap sections 1h
  (`seo:sitemap:{section}`), PSEO counts 1h tetap + `Cache-Control: public`.
- Redis produksi: `QUEUE/CACHE/SESSION` via env + verifikasi di `DEPLOY.md`.
- P3: skeleton loading (`jk-skeleton` + `wire:loading`) di discover, matches,
  chat inbox, visitors; bulk moderasi admin (`bulk-decide` ≤100 item +
  checkbox di antrean, kolom `reviewer_id` baru).
- Sisa P2: K4 webhook flood (duplikat ×5 + capture/settlement out-of-order +
  stale pending → tepat 1 aktivasi, `WebhookFloodTest`); `scale:probe`
  (ms/query + ukuran tabel unbounded, default rollback); Scout terinstal
  (`SCOUT_DRIVER=null` no-op, index publik-saja siap flip); resep Reverb
  multi-node + Search + keputusan partisi di DEPLOY/runbook.

### Fixed
- Queue worker poisoning (retry selamanya) via batas eksplisit; timeout
  pekerjaan berat tidak lagi menahan worker.
- Analytics admin tanpa cache (COUNT mentah per load) → cache ber-TTL.
- Dead read path `profile_views` (event tanpa writer) → writer throttled.
- Thin-pool kota kecil (hasil kosong) → backfill radius.
- `reviewer_id` moderasi tidak tersimpan (kolom belum ada) → migrasi aditif.

## [1.4.0] — 2026-09-23

### Added
- Intelligence: `MatchExplanation` + `why-match`, `PersonalizationService`,
  catatan match privat, bookmark pesan, AI profile tips, digest taaruf,
  reminder mingguan match/taaruf, trust badge granular, CTA kontekstual.
- Commercial: `jodohku:demo` 5000 user, provider foto aman, `demo:reset`,
  license gate, halaman panduan, hapus akun.
- SEO/GEO/PSEO: metadata terpusat, sitemap dinamis, PSEO kota/topik +
  quality gate, profil publik opt-in.

### Fixed
- Rematch setelah unmatch, kuota re-like, entitlement langganan tunggal,
  kontrak unread per-percakapan, broadcast template, pagination discovery
  lossless, N+1 hard-filter/weights/settings, foto privat & bio di API.

## [1.3.x] — hardening & chat expansion (lihat riwayat git)

- Idempotensi payment/webhook, moderasi Bahasa Indonesia, voice/video call,
  scheduled/disappearing message, poll/stiker/translate, chaperone taaruf.
