# TROUBLESHOOTING — Jodohku

## Instalasi

- `Vite manifest not found`: jalankan `npm install && npm run build`.
- Migrasi gagal di tengah: `php artisan migrate:status`, perbaiki, ulangi
  (migrasi idempoten; jangan edit migrasi yang sudah ter-deploy).

## Demo (`DEMO.md`)

- `existing demo users … --force`: reset dulu
  (`jodohku:demo:reset --confirm`) atau pakai `--force`.
- Foto lambat: seed dulu `--no-photos`, lalu `jodohku:demo:photos` terpisah.
- MySQL `max_allowed_packet`: chunk default 500 baris; kecilkan `--users`.

## Antrean / Reverb / Scheduler

- Chat/AI/notifikasi macet: pastikan `queue:work` jalan; cek `jobs`/`failed_jobs`.
- Realtime mati: cek `reverb:start`, `REVERB_*` env, dan `routes/channels.php`
  (blokir = tolak otorisasi channel).
- Skor harian basi: pastikan scheduler jalan (`GenerateDailyMatches` 01:00).

## Pembayaran

- Webhook 4xx: cek signature gateway + `webhooks` throttle; idempoten via
  `event_id` — kirim ulang aman.
- Double charge dicurigai: cek `payments` dengan `gateway_response.idempotency_key`.

## SEO

- Halaman tak ter-index: cek `seo.robots_index`, eligibility PSEO
  (`/biro-jodoh/{kota}` 404 = di bawah threshold), `seoSchemas` bila
  `schema_enabled` mati.
- Sitemap basi: TTL 1 jam; tersimpan otomatis saat settings SEO disimpan.

## Test

- `php artisan test` memakai SQLite memori; MySQL dev hanya untuk demo manual.
- Pint gagal: jalankan `vendor/bin/pint` (tanpa `--test`) lalu commit.
