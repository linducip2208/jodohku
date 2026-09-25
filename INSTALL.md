# INSTALL — Jodohku (Lokal)

## Syarat

- PHP 8.3 + ext sqlite/pdo, mbstring, openssl, fileinfo
- Composer 2, Node 20+, SQLite (dev) / MySQL 8 (prod opsional)

## Langkah

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # jika memakai sqlite
php artisan migrate --seed
npm install
npm run dev          # terminal 1
php artisan serve    # terminal 2
php artisan reverb:start --host=localhost --port=8080  # terminal 3
php artisan queue:work --queue=default  # terminal 4
php artisan schedule:work               # terminal 5 (dev)
```

Buka `http://localhost:8000`. Akun seed: `superadmin@jodohku.example.test` / `admin@…` / `moderator@…` / `operator@…` / member `andi.pratama@example.test`, password `password`.

## Verifikasi

```bash
php artisan migrate:fresh --seed --force
php artisan test   # 277 passed
```

## Masalah umum

- `Route [register] not defined` → sudah diperbaiki via `routes/web.php` (named `register`, `login`).
- `User::createToken undefined` → `HasApiTokens` sudah terpasang di `App\Models\User`.
- Broadcast error lokal → set `BROADCAST_CONNECTION=log` bila tanpa Reverb.
