# DEPLOY — Jodohku

## Build

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan migrate --force
```

## Proses wajib

| Proses | Perintah | Catatan |
|---|---|---|
| Web (php-fpm/nginx) | `php artisan serve` → ganti php-fpm | `APP_URL` https produksi |
| Queue | `php artisan queue:work --queue=default --tries=3 --backoff=30` | supervisord/systemd, 2–4 worker |
| Scheduler | `* * * * * php artisan schedule:run` | expire subs/boost/credit, daily matches |
| Reverb | `php artisan reverb:start --host=0.0.0.0 --port=8080` | di belakang TLS reverse-proxy |
| Horizon (opsional) | bila migrasi ke redis | `QUEUE_CONNECTION=redis` |

Gunakan `QUEUE_CONNECTION=redis` + `BROADCAST_CONNECTION=reverb` + `CACHE_STORE=redis` di produksi.

## Redis (P0 — wajib produksi)

Single MySQL tidak boleh merangkap queue + cache + session saat scale
(SCALE-AUDIT C1). Produksi memakai Redis untuk ketiganya; dev boleh tetap
`database` agar tanpa dependensi.

```bash
# .env produksi
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
# REDIS_URL=redis://:password@host:6379/0  # alternatif single-URL
```

Verifikasi setelah deploy:

```bash
php artisan tinker --execute="Cache::put('deploy:ping', 1, 60); print_r([Cache::get('deploy:ping'), config('queue.default'), config('cache.default'), config('session.driver')]);"
php artisan queue:monitor redis:default --max=1000
```

Catatan:

- Reverb multi-node (P2) memakai adapter Redis yang sama; single-node
  produksi tidak butuh config tambahan selain `BROADCAST_CONNECTION=reverb`.
- Session di Redis = stateless web nodes, tanpa sticky session.
- Semua 20 jobs sudah punya `tries/timeout/backoff`
  (`app/Jobs/Concerns/HasScaleLimits.php`): payment webhook 5× dengan
  backoff panjang, batch harian 2×/30 menit, sisanya 3×/120 detik.
- AI global: `AI_MONTHLY_CAP_USD` (default 50) + `AI_KILL_SWITCH=true`
  mematikan semua provider call tanpa deploy ulang.

## Failed jobs ops

```bash
php artisan queue:failed              # daftar gagal
php artisan queue:retry <uuid>        # retry satu
php artisan queue:retry all           # retry semua (hati-hati payment!)
php artisan queue:flush               # buang semua yang gagal
php artisan queue:prune-failed --hours=168  # retention 7 hari (cron mingguan)
```

Monitor: `failed_jobs`, `payment_webhooks.is_processed=false`, `ai_usage_logs`
(spend bulanan vs `AI_MONTHLY_CAP_USD`). Alert bila queue depth > 1000
(`queue:monitor`) atau failed_jobs bertambah > 10/jam.

## Reverb + SSL

- Terminasi TLS di nginx/caddy; Reverb internal `http`, publik `https`.
- Env: `REVERB_HOST`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_APP_KEY/SECRET/ID` sinkron dengan `VITE_*`.
- Batasi origin via `config/reverb.php` `allowed_origins` bila perlu.

## Checklist

- `APP_DEBUG=false`, `APP_KEY` terisi, `DB_*` MySQL, `REDIS_*` terisi.
- `php artisan storage:link`, permission `storage/`, `bootstrap/cache/`.
- Webhook gateway mengarah ke `https://domain/api/v1/webhooks/{gateway}`.
- Monitor: `failed_jobs`, `payment_webhooks.is_processed=false`, `ai_usage_logs`.
