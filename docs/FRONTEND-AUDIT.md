# FRONTEND AUDIT — Jodohku (2026-09-25, HEAD 49e4149)

## Inventarisasi

- Layouts: `landing`, `member` (sidebar + bottomnav + modal + toast + focus-trap + Escape), `admin`, `mail`. Tidak ada duplikat.
- Komponen: `profile-card`, `social-post`, `empty`/`empty-admin`, `why-match`, `faq-list`, `seo`, `stat-card`. Reuse konsisten.
- Livewire: discover-grid, like-buttons, match-list, chat-inbox/window, visitor-list, notification-bell, profile-completeness, ai-assistant-box + admin (4). Semua dengan skeleton `wire:loading`.
- CSS: `app.css` (Tailwind v4) + `member.css` (~160 baris, design token `--jk-*`, dark via `data-theme` + media fallback, reduced-motion). Landing memakai keduanya.
- Vite build PASS (manifest + hash valid, CSS 200).
- Tidak ada TODO/FIXME/lorem/coming-soon di views.

## Yang sudah modern (JANGAN dirombak)

Landing product-first (data demo real), auth, discover + filter sheet,
chat 2-pane + read receipt + reply + composer sticky, profile tabs +
privasi, community + composer foto, notifikasi berkategori + deep-link,
bottom nav + sidebar, dark mode, skeletons, empty states.

## Temuan (aksi)

1. **Rewind tanpa UI web** — `LikeService::rewind()` + API ada, tombol tidak
   ada. → route web + tombol di halaman likes.
2. **Tanpa selebrasi match di web** — hanya teks status + email. → modal
   di `like-buttons` saat `is_new_match`, CTA real (/matches + profil).
3. **Plans 1 kolom di desktop** — override inline `grid-template-columns:1fr`
   mematikan grid responsif. → hapus override.
4. **Composer chat `background:#fff` inline** — pecah di dark mode.
   → `var(--jk-card)` + `var(--jk-line)`.
5. Onboarding khusus tidak ada — tercakup profile-completeness + kuesioner;
   tidak dibuat (hindari duplikat).

## Non-temuan (sengaja tidak diubah)

- Discover tanpa paginasi cursor (loadMore + limit, sesuai desain).
- Likes page query inline Blade (pola existing, paginate manual per tab).
- `why-match` peach tint: kontras aman di dark (teks gelap di atas terang).
