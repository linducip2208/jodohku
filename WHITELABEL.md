# WHITELABEL — Jodohku Tier 3 (self-service)

Jual codebase yang sama ke banyak klien dengan brand berbeda.

## Konsep

- Tabel `brands`: slug, nama, tagline, 2 warna, logo, favicon, domain,
  aktif/default, feature flags, footer.
- Resolusi: domain request → brand default → fallback Jodohku.
  Cache 1 jam (`brand:host:*`, bust otomatis saat admin simpan).
- Tanpa brand aktif: tampilan 100% Jodohku seperti semula.

## Onboarding klien (4 langkah, staff)

1. Buka `/admin/brands/wizard`.
2. Isi identitas → warna (live preview) → logo/favicon → fitur.
3. Klik "Simpan draf & preview landing" — landing tampil dengan brand
   draf dalam iframe (tanpa menyimpan).
4. "Buat brand" — aktifkan langsung atau nanti.

## Operasional

- Daftar/edit: `/admin/brands` (logo ≤2MB, favicon ≤1MB, hex `#rrggbb`).
- Pindah server: Export (.zip berisi `brand.json` + aset) → Impor di
  server tujuan (+ opsi jadikan default).
- Preview brand tersimpan: `/?preview_brand={id}` (staff only).
- Feature flags per-brand (taaruf/konselor/komunitas/events/gifts/boost):
  ditegakkan backend via `brand.feature` (404, tanpa bocor) + link
  navigasi disembunyikan. Tanpa brand aktif = ikut flag global.

## P1 — Enforce, atribusi, role klien

- `users.brand_id` diisi otomatis dari domain saat registrasi
  (web + API + controller) → dasar hitung user per klien.
- Role `client` (rank 30, bukan staff): kelola HANYA brand sendiri
  (lihat/edit/export); tanpa brand terikat = 403 total.
  Buat/hapus/impor tetap admin.
- Gate `brand-manager` + `BrandPolicy` (viewAny/view/update/delete).

## Keamanan domain & kuota lisensi

- Setiap brand ber-domain dapat token verifikasi otomatis.
  Buktikan kepemilikan via DNS TXT **atau** file HTTP
  (ditayangkan app ini di `/.well-known/brand-verification.txt`).
- Tombol "Cek verifikasi" di form brand; ganti domain me-reset status.
- Mode ketat `BRAND_REQUIRE_VERIFICATION=true`: domain tak terverifikasi
  tidak resolve (fallback Jodohku). Default false (peringatan di admin).
- `max_users` ditegakkan saat registrasi (422 bila penuh).

## Paket jualan (siap demo ke klien)

- **Stats per brand** (`/admin/brands/{brand}/stats` + API):
  user, verified, premium, aktif 7d, matches, pesan, revenue —
  dasar penagihan ke klien.
- **Template gallery**: 5 preset (Jodohku, Islami, Premium, Playful, Navy)
  sekali-klik di wizard.
- **Ikon PWA otomatis**: tiap upload logo → icon-192/512/maskable +
  apple-touch di `brands/{slug}/icons/`; manifest memakai itu dulu.
- **Copy landing per brand**: hero title/subtitle/CTA di form brand.
- **Lisensi**: `expires_at` + `max_users`; kedaluwarsa → fallback
  Jodohku otomatis; stats tampilkan status lisensi & kuota.

## File kunci

- `app/Services/BrandService.php` (theme, resolve, export/import)
- `app/Http/Middleware/ResolveBrand.php` (share `$brandTheme`)
- `app/Http/Controllers/Admin/BrandController.php`
- `resources/views/components/brand-logo.blade.php`
- `resources/views/admin/brands/{index,form,wizard}.blade.php`
- `database/migrations/2026_09_28_000001_create_brands_table.php`
- Test: `tests/Feature/BrandWhitelabelTest.php`
