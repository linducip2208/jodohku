# CHANGELOG — Jodohku

Format: `Added / Changed / Fixed / Security`. Version di `config/app.php`
(`APP_VERSION`).

## [Unreleased] — dating frontend polish

### Added
- Tombol rewind web (`POST /rewind`, throttle) di halaman likes —
  backend `LikeService::rewind` akhirnya punya UI.
- Modal selebrasi match di `like-buttons` (like & superlike): CTA real
  ke `/matches` + profil pasangan. Terverifikasi browser end-to-end.

### Fixed
- Paket Premium 1 kolom di desktop → grid responsif `.jk-grid`.
- Composer chat inline `background:#fff` → token dark-mode.
- Tulis `docs/FRONTEND-AUDIT.md` (inventaris + temuan + non-temuan).

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
