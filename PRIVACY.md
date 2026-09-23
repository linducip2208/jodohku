# PRIVACY — Jodohku

## Matriks singkat

```text
Email/HP            : pemilik + staf. API: hanya self/staff. SEO/AI: tidak pernah.
Bio                 : ikut bio_visibility (public/members_only/…).
Foto privat/pending : pemilik + staf. API/Blade/avatar disaring; URL tak-tertebak.
Usia/lokasi/online  : ikut visibility setting (API + Blade konsisten).
Preferensi/kuesioner: tak pernah ke API publik, AI, SEO, atau alasan match.
Catatan match       : hanya penulis. Bookmark: hanya pemilik.
Chat                : hanya participant (+staf moderasi). Attachment via controller ber-otorisasi.
Konselor            : hanya report yang eksplisit dibagikan untuk booking itu.
Profil publik /u/*  : OPT-IN (default 404/noindex); tamu hanya lihat field public.
Hapus akun          : soft delete + status deleted + token dicabut + audit.
Demo                : is_demo; reset tak menyentuh user asli.
```

## Prinsip AI & SEO

AI hanya menerima data yang memang boleh dilihat peminta; prompt digrounded
(data terstruktur), respons tak boleh mengarang atribut/verifikasi/lokasi/skor.
PSEO hanya agregat; schema publik tanpa PII.
