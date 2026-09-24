# OPS RUNBOOK — Jodohku (scale P0–P1)

## Queue

- Produksi: `QUEUE_CONNECTION=redis`, 2–4 worker
  `php artisan queue:work --queue=default --tries=3 --backoff=30`.
- Semua jobs punya `tries/timeout/backoff` eksplisit
  (`app/Jobs/Concerns/HasScaleLimits.php`); payment 5×, batch 2×.
- `php artisan queue:monitor redis:default --max=1000` → alert bila > 1000.
- Gagal: `queue:failed` / `queue:retry <uuid>` / `queue:retry all`
  (hindari retry massal payment tanpa cek) / `queue:flush` /
  `queue:prune-failed --hours=168` mingguan.

## Retention (scheduler)

| Job | Jadwal | Apa |
|---|---|---|
| `CleanupOldSessions` | 03:00 | sessions 30d, cache expired |
| `PruneStaleData` | 03:30 | notif read 90d/semua 365d, audit 180d, failed 30d, AI log 365d, views 180d, scores stale 90d, jobs stuck 7d |
| `GenerateDailyMatches` | 01:00 | batch harian (2 tries/30 mnt) |
| expiry subs/boost/credit/verif | hourly/daily | lihat `routes/console.php` |

## AI cost

- Cap: `AI_MONTHLY_CAP_USD` (default 50), cache spend 5 mnt
  (`ai:spend:YYYYMM`). Kill: `AI_KILL_SWITCH=true` → semua `chat()` tolak.
- Monitor: `SELECT SUM(cost) FROM ai_usage_logs WHERE created_at >= awal_bulan`.
- Alert pada 80% cap; cek `ai_usage_logs` bila anomali (purpose/provider).

## Cache

- Analytics admin: kpi 60s, overview/engagement 120s, gateways/top/cohorts/
  funnel 300s. Dashboard: metrics/charts 60s. Sitemap sections 1h.
- Bust SEO: `SeoService::bust()` (sitemap + pseo cities) dipanggil dari
  `SettingsController` saat konten SEO berubah.

## Reverb / broadcast

- Single node default; multi-node (P2) butuh adapter Redis + sticky LB —
  belum diaktifkan (tunggu K5: 5k koneksi konkuren). Monitor koneksi +
  `TypingIndicator` storm via client throttle.

## Load tests (sebelum klaim siap)

K1 discover 200 konkuren p95 < 800ms @100k · K2 100 msg/s · K3 500 signup/
mnt · K4 webhook flood duplikat · K5 5k koneksi Reverb · K6 daily picks
@100k. Seed: `php artisan jodohku:demo --users=100000`.

## P2 ditunda (terukur dulu)

Meilisearch/Scout (search > ~100k), partisi time `messages`/`audit_logs`,
Reverb multi-node, S3/CDN media — hanya bila K1–K6 membuktikan sakit.
