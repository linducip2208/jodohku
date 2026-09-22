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
| POST | `/conversations/{conversation}/messages` | sanctum | kirim pesan (`client_message_id` idempoten; kuota per peer: free 1, premium 30 → 429 + `upgrade`) |
| GET | `/chat/quota?user_id=` | sanctum | sisa kuota pesan ke user (`limit/used/remaining`) |
| GET | `/chat/themes`, `/chat/stickers` | sanctum | preset tema & katalog stiker |
| POST | `/conversations/{conversation}/scheduled-messages` | sanctum | jadwalkan pesan (maks 30 hari; kuota/moderasi saat terkirim) |
| GET | `/conversations/{conversation}/scheduled-messages` | sanctum | daftar terjadwal milik sendiri |
| DELETE | `/scheduled-messages/{id}` | sanctum | batalkan terjadwal (pending saja) |
| PATCH | `/conversations/{conversation}/disappearing` | sanctum | pesan menghilang `{seconds 1jam–30hari}`/null |
| POST/GET | `/messages/{message}/poll/vote`, `/messages/{message}/poll` | sanctum | vote & hasil polling (1 suara/user, bisa ganti) |
| POST | `/messages/{message}/translate` | sanctum | terjemahan AI id/en (fallback teks asli) |
| GET | `/conversations/{conversation}/catch-up` | sanctum | ringkasan pesan belum dibaca |
| GET | `/chat/{conversation}/safety` | sanctum | level risiko peer + tips aman |
| GET | `/calls/rates` | sanctum | tarif token voice/video per menit |
| POST | `/conversations/{conversation}/calls` | sanctum | undang call voice/video (berbayar token; tolak bila saldo < 1 menit) |
| GET | `/conversations/{conversation}/calls` | sanctum | riwayat call |
| POST | `/calls/{call}/accept`, `/reject`, `/cancel`, `/end` | sanctum | kelola call (tagihan per menit dibulatkan ke atas) |
| POST/DELETE | `/courtships/{id}/chaperone` | sanctum | tambah/hapus wali read-only (mulai tahap taaruf) |
| GET | `/courtships/journey/{partner}` | sanctum | marriage journey dari data aktual (match/chat/stage/konseling/wali/nikah) |
| GET | `/consultations/counseling` | sanctum+konselor | booking yang ditugaskan + laporan yang dibagikan eksplisit |
| GET | `/ai/taaruf-topics/{user}` | sanctum | topik taaruf grounded + pertanyaan siap pakai |
| GET | `/health` | publik | status aplikasi + database (tanpa secrets) |
| POST | `/conversations/{conversation}/attachments` | sanctum | upload foto/video/audio/PDF + kirim sebagai pesan (throttle chat-upload) |
| POST | `/conversations/{conversation}/typing` | sanctum | indikator mengetik (broadcast Reverb) |
| PATCH | `/conversations/{conversation}/settings` | sanctum | `is_muted/is_pinned/is_archived/theme/nickname` |
| GET | `/conversations/{conversation}/search?q=` | sanctum | cari dalam satu percakapan (abaikan pesan terhapus) |
| GET | `/conversations/{conversation}/export` | sanctum | ekspor thread (abaikan pesan terhapus) |
| POST | `/chat/{conversation}/mark-all-read` | sanctum | tandai semua dibaca (global user) |
| GET/POST/DELETE | `/chat/{conversation}/labels[/{labelId}]` | sanctum | kelola label pribadi |
| POST/DELETE | `/messages/{message}/reactions` | sanctum | tambah/hapus reaksi emoji (idempoten) |
| DELETE | `/matches/{user}` | sanctum | unmatch (nonaktif + `unmatched_at`) |
| POST | `/chat-requests/{user}` | sanctum | kirim permintaan chat (idempoten pending dua arah, tolak bila diblokir, cap harian) |
| GET | `/forums/search?q=` | sanctum | cari thread + post + forum |
| PUT/DELETE | `/forum-threads/{thread}`, `/forum-replies/{reply}` | sanctum | edit/hapus milik sendiri (staff boleh; locked → 422) |
| GET | `/matches/{user}/score-cache` | sanctum | skor kompatibilitas + cache |
| GET | `/gifts/leaderboard?period=`, `/gifts/trending` | sanctum | top pengirim/penerima + gift terlaris |
| GET/POST | `/courtships`, `/courtships/{id}` | sanctum | tahapan taaruf kenalan→taaruf→khitbah→menikah (butuh match aktif; khitbah wajib restu wali) |
| POST | `/courtships/{id}/advance`, `/withdraw`, `/guardian/approve` | sanctum | naik tahap, mundur, setujui wali |
| PUT | `/courtships/{id}/guardian` | sanctum | data wali (nama/telp/hubungan) |
| GET | `/counselors` | sanctum | konselor biro jodoh aktif |
| GET/POST | `/consultations` | sanctum | booking konsultasi (anti double-book; jadwal masa depan) |
| POST | `/consultations/{id}/confirm`, `/complete`, `/cancel` | sanctum | kelola status (konselor/staff; transisi dijaga) |
| GET/POST | `/compatibility-reports` | sanctum | generate laporan kecocokan dari MatchingEngine (idempoten) |
| GET | `/success-stories`, `/success-stories/mine` | sanctum | kisah sukses published + milik sendiri |
| POST | `/success-stories` | sanctum | kirim kisah (moderasi admin; min 50 karakter) |
| POST | `/events/{event}/rsvp` | sanctum | `{status: confirmed/declined/maybe}` (default confirmed) |
| POST | `/ai/rewrite` | sanctum | tulis ulang draf (`tone`: friendly/funny/formal/romantic/confident) |
| GET | `/ads?placement=` | sanctum | iklan servable (aktif + dalam jadwal) |
| GET | `/ads/stats` | sanctum | impresi/klik/CTR per placement |
| POST | `/ads/{ad}/impression`, `/ads/{ad}/click` | sanctum | catat view/klik (hanya bila servable) |
| GET | `/boost/status`, `/boosts/history` | sanctum | status live + sisa waktu; riwayat boost |
| POST | `/coupons/quote` | sanctum | validasi kupon + hitung diskon tanpa checkout |
| POST | `/checkout/quote` | sanctum | preview total (garansi sama dengan checkout) |
| GET | `/payments`, `/payments/summary` | sanctum | riwayat pembayaran + ringkasan bulan berjalan |
| GET | `/verification/status` | sanctum | status pengajuan verifikasi per tipe |
| GET | `/wallet/summary` | sanctum | saldo + lifetime + arus bulan berjalan |
| GET | `/wallet/transactions?type=&from=&to=` | sanctum | filter tipe + rentang tanggal |
| GET | `/subscriptions/trial-eligibility` | sanctum | cek jatah trial (sekali selamanya) |
| POST | `/subscriptions/switch` | sanctum | ganti plan segera |
| POST | `/webhooks/{gateway}` | publik (HMAC, fail-closed) | `ipaymu/xendit/midtrans/tripay`; idempoten via `event_id`; tanpa signature → 400 |
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
