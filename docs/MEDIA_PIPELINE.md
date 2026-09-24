# MEDIA PIPELINE

- Abstraksi: disk `public` (env `FILESYSTEM_DISK`, S3-compatible siap via
  env — URL via `asset('storage/…')` / Storage URL, CDN-ready).
- Validasi sync: MIME via finfo (bukan ekstensi), ukuran (foto 8MB, story
  video 32MB), dimensi minimum, hash duplikat (foto profil).
- Berat di queue: `ProcessMediaUpload` (resize ≤1600px, JPEG 82,
  best-effort — original tetap dipakai bila gagal). Foto profil diproses
  sync + thumbnail 400px (`PhotoService`, pola yang sama).
- Metadata: pertimbangkan strip EXIF bila memuat GPS — jalur upload
  me-re-encode via intervention (menjatuhkan sebagian besar metadata).
- Tidak ada binary di MySQL (hanya path). Private media: `is_private` +
  `PhotoService::visibleTo` + avatar yang tidak pernah fallback ke
  foto privat/tak-disetujui.
- Story video: disimpan apa adanya (tanpa transcode — transcode berat
  butuh worker khusus, di luar scope; dibatasi 32MB + tipe mime).
