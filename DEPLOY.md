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

## Reverb + SSL

- Terminasi TLS di nginx/caddy; Reverb internal `http`, publik `https`.
- Env: `REVERB_HOST`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, `REVERB_APP_KEY/SECRET/ID` sinkron dengan `VITE_*`.
- Batasi origin via `config/reverb.php` `allowed_origins` bila perlu.

## Checklist

- `APP_DEBUG=false`, `APP_KEY` terisi, `DB_*` MySQL, `REDIS_*` terisi.
- `php artisan storage:link`, permission `storage/`, `bootstrap/cache/`.
- Webhook gateway mengarah ke `https://domain/api/v1/webhooks/{gateway}`.
- Monitor: `failed_jobs`, `payment_webhooks.is_processed=false`, `ai_usage_logs`.
