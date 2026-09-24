# FEED ARCHITECTURE (`FeedService`)

Pipeline per request (controller tipis, Blade tanpa query):

```text
Candidates (bounded window, latest 300)
  → Filtering (visibility scope, blocked/muted authors, incognito)
  → Safety (is_hidden, counselors stay for community content)
  → Scoring (recency 100/h + engagement cap 50 + following 60
             + my-groups 45 + boost 80)
  → Ranking (sort desc)
  → Pagination (LengthAware, ?page= — kompatibel Blade)
```

- Eager loads tetap: user, comments(3)+user, hashtags, counts.
- `ReactionService::prime()` menambah state viewer + reply lists dalam
  3 query bulk (independen dari ukuran halaman) — komponen Blade membaca
  atribut dan melewati query per-baris bila ada.
- Trending = kecepatan engagement 72 jam (`FeedService::trending`).
- Boost postingan (kredit, 24 jam) hanya menaikkan ranking + label
  "Boost" — tidak mengubah skor kompatibilitas apa pun.
- Home memakai `FeedService::feed` via `HomeController` (inline query
  lama dihapus); `/komunitas` memakai query yang sama + prime.
