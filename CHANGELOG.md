# CHANGELOG — Jodohku

Format: `Added / Changed / Fixed / Security`. Version di `config/app.php`
(`APP_VERSION`).

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
