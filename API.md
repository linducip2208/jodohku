# API — `/api/v1`

Base: `/api/v1`. Auth Sanctum bearer (`POST /auth/*` → `token`).

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/auth/register` | publik | name, email, password+confirmation → user + token (201), fire `UserRegistered` |
| POST | `/auth/login` | publik | email+password → token (422 bila salah) |
| POST | `/auth/2fa/verify` | publik | `{user_id, code}` → token (wajib bila user aktifkan 2FA; login mengembalikan `two_factor_required`) |
| POST | `/2fa/enable`, `/2fa/disable` | sanctum | aktif/nonaktif 2FA (disable perlu `current_password`) |
| GET | `/auth/me` | sanctum | profil ringkas + role + premium |
| POST | `/profile/photos` | sanctum | upload foto (JPG/PNG/WebP ≤8MB, min 200×200, anti-duplikat; status pending → antrian moderasi; hanya approved yang publik) |
| GET | `/profile/{user}` | sanctum+policy | profil orang lain (foto pending disembunyikan; block → 403) |
| GET | `/discover` | sanctum | feed `DiscoveryService` (`per_page`, filter gender/city/min_age/max_age/verified/online/sort); `data[]` + `meta.next_cursor` |
| GET | `/who-liked`, `/visitors` | sanctum+premium | 403 + `upgrade:true` untuk free |
| GET | `/likes/quota` | sanctum | `{remaining\|"unlimited", is_premium}` |
| POST | `/checkout` | sanctum | `gateway` + `subscription_plan`/`credit_product` + opsional `coupon_code` (diskon persen/nominal, redeem atomik; kupon tak valid → 422 tanpa orphan payment) |
| GET | `/admin/overview` | sanctum+`admin` | `users_total`, `reports_pending` (403 untuk member) |
| GET | `/admin/gateways` | sanctum+`can:admin` | daftar gateway; sekret dimask `***encrypted***` |
| POST | `/admin/gateways/{code}` | sanctum+`can:admin` | simpan setting; sekret dienkripsi `Crypt` |
| POST | `/webhooks/{gateway}` | publik (HMAC) | `ipaymu/xendit/midtrans/tripay`; idempoten via `event_id` |
| POST | `/admin/reports/{report}/resolve` | staff `moderator` | selesaikan laporan + audit log |
| GET | `/blog`, `/blog/{slug}` | sanctum | artikel published (view_count auto-increment) |
| GET | `/forums`, `/forums/{slug}` | sanctum | daftar forum & thread |
| POST | `/forums/{slug}/threads` | sanctum | buat thread (throttle 10/mnt) |
| GET | `/forum-threads/{thread}` | sanctum | thread + balasan |
| POST | `/forum-threads/{thread}/replies` | sanctum | balas (dikunci → 422; throttle 30/mnt) |

Web: `/` (landing), `/discover`, `/register`, `/login`, `/logout`.

## Contoh

```bash
curl -X POST /api/v1/auth/register -H 'Content-Type: application/json' \
 -d '{"name":"Ayu","email":"ayu@example.test","password":"password123","password_confirmation":"password123"}'
curl /api/v1/discover?per_page=5 -H "Authorization: Bearer <token>"
curl -X POST /api/v1/webhooks/midtrans -H 'Content-Type: application/json' -d '{...}'
```
