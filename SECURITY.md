# SECURITY — Jodohku

## Model ancaman yang ditangani

- Auth: session web + Sanctum API, 2FA email-OTP + TOTP authenticator
  (RFC 6238, backup codes sekali-pakai), throttle login/register/2FA
  (termasuk per-user+IP), OTP telepon HMAC (bukan plaintext),
  password reset bertanda tangan + throttle, verifikasi email bertanda tangan.
- Privasi lokasi: jarak di-fuzz 0.5km, koordinat exact tak pernah diekspos.
- Push: default `log` (aman); FCM v1 OAuth2 bila dikonfigurasi.
- Otorisasi: Policies (`User/Conversation/Message/Payment/Report/Courtship/
  Consultation/...`) + Gates RBAC (`member/premium/operator/moderator/admin/
  superadmin/staff`) di SEMUA rute sensitif; staff tidak bisa retry payment
  milik orang lain; note/bookmark/digest terikat participant.
- IDOR: diuji regresi (attachment, foto privat, pesan, viewer, retry payment,
  digest taaruf, profil opt-in).
- Upload: verifikasi MIME asli (finfo), allowlist ekstensi, tolak
  php/exe/js/html/svg, batas ukuran, path tak-tertebak (`uniqid` 23 char),
  foto privat tak pernah masuk fallback avatar publik.
- Payment: HMAC gateway, webhook idempoten (`event_id` + lock), fulfill
  sekali-eksekusi, refund gateway-dulu, sekret terenkripsi (`Crypt`), hanya
  superadmin yang kelola kredensial.
- Abuse: throttle per-rute ber-prefix unik, kuota chat/AI premium-aware,
  moderasi lokal-first + antrean AI review, audit log tanpa sekret.

## Yang harus operator lakukan

- `APP_DEBUG=false`, `APP_KEY` unik per install, scheduler + queue + Reverb
  jalan (lihat DEPLOY.md), backup DB + `storage/` terenkripsi, rotasi
  kredensial gateway bila bocor, pantau `/health` dan `failed_jobs`.
