# DEMO — Jodohku Commercial Demo Dataset

One-command, production-shaped demo data for buyers, demos, and load testing.
Everything is created through the **real tables** (bulk inserts) and the **real
MatchingEngine** (scores for matched pairs). No mock APIs, no UI-only fakes.

## Quick start

```bash
php artisan migrate
php artisan db:seed          # base data: plans, gifts, questions, admins
php artisan jodohku:demo --users=5000
```

Demo login password for **every** demo account: `password`
Demo emails look like `demo00001@demo.jodohku.test` (never collide with real users).

## Commands

```bash
php artisan jodohku:demo --users=5000 --seed=20260923
php artisan jodohku:demo --users=1000
php artisan jodohku:demo --users=10000 --no-photos
php artisan jodohku:demo:photos --users=5000        # (re)generate avatars only
php artisan jodohku:demo:photos --dry-run
php artisan jodohku:demo:photos --force
php artisan jodohku:demo:reset --confirm            # delete demo data only
php artisan license:status                          # commercial license gate
```

### `jodohku:demo` options

```text
--users=5000        number of demo users (default from DEMO_USERS)
--seed=20260923     deterministic seed (same seed → same relationships)
--photos=7500       total avatars (default users × DEMO_PHOTOS_PER_USER=1.5)
--messages=50000    total messages (default users × 10)
--posts=2000        total group/community posts (default users × 0.4, min 200)
--comments=10000    total comments (default posts × 5)
--groups=50         total groups (default users/100, min 10)
--events=100        total events (default users/50, min 20)
--forums=1000       total forum threads (default users/5, min 200)
--fresh             migrate:fresh + db:seed first (DESTRUCTIVE, asks unless --force)
--force             allow production + wipe existing demo data first
--no-photos / --no-chat / --no-community / --no-taaruf / --no-payments
--dry-run           print the plan, write nothing
```

### Safety rules

- Refuses production without `--force`; refuses when demo data exists without `--force`.
- `demo:reset` refuses without `--confirm` (and needs `--force` in production).
- Reset deletes **only** `users.is_demo = true` rows (+ FK cascades) plus
  `demo-*` slugs and the `demo/` storage directory. Real users are never in
  any `WHERE` clause. Admin can filter demo accounts (`?demo=1`) — demo rows
  carry a Demo badge.

## What gets generated (measured run: 5000 users, seed 20260923)

```text
Users:          5000    (70% free / 22% premium / 7% VIP-ish, 32% verified,
                         ~50% recently active; 20 Indonesian cities)
Profiles:       5000    preferences, privacy, notification prefs, 2–5 interests
Photos:         7500    generated avatars (see Photo provider)
Likes:          29600   (5% super) + favorites + 15000 profile views
Matches:        400     canonical rows + REAL MatchingEngine scores persisted
Visitors:       14998
Conversations:  6250    (all matched pairs + top-up pairs, linked match_id)
Messages:       56380   Indonesian themed dialogues, read receipts ~70%
Notifications:  11947   MatchFound + NewMessage shapes (real classes)
Posts:          2000    Comments: 10223  Groups: 50  Events: 100
Forums:         1000 threads + replies   Blogs: 5
Taaruf:         140 courtships (kenalan/taaruf/khitbah + guardians) + reports
Subscriptions:  1348    (premium_monthly/vip_monthly, paid manual payments)
Credits:        1775 wallets + bonus transactions   Gifts: 600   Boosts: ~150
Safety:         blocks ~1%, reports, approved verification requests
Counselors:     3 demo counselors + ~120 consultations
```

## Determinism

`mt_srand($seed)` drives every relationship decision (who likes whom, pairs,
stages, content rotation). Same seed → same graph. Row UUIDs/ULIDs and
`created_at` wall-clock spread intentionally vary per run.

## Photo provider

Default driver `generated` renders abstract gradient avatars with GD locally —
no people, no copyrighted material, no network. Safe to redistribute inside a
commercial source package. Files live on the `public` disk (S3-compatible via
`FILESYSTEM_DISK`) under `demo/avatars/`, rows pass through the normal
`profile_photos` table (`status=approved`, sha1 hash, dimensions).

Opt-in remote driver (operator owns the source licensing):

```env
DEMO_PHOTO_DRIVER=remote
DEMO_PHOTO_URL_TEMPLATE=https://example.test/avatar/{seed}
```

Remote downloads are cached by URL hash (`demo/remote/`), retried, rate
limited, MIME/dimension validated, and fall back to generated avatars on
failure — seeding never hard-fails on photos. Regenerate with
`jodohku:demo:photos --force`; preview with `--dry-run`.

`jodohku:demo:photos` honors `DEMO_PHOTOS_PER_USER` (default 1.5): every
user gets 1 photo and ~half deterministically get a 2nd (index 1), with
correct `sort_order`/`is_primary`. Existing slots are topped up, never
duplicated.

## Synthetic-face library (`local_library` driver)

For realistic Southeast Asian-style profile photos without touching real
people's faces or stock licensing, point the provider at a local folder
of **synthetic faces**:

```env
DEMO_PHOTO_DRIVER=local_library
DEMO_PHOTO_LIBRARY=demo/faces
```

Folder structure (paths relative to the `local` disk, i.e.
`storage/app/demo/faces/`; override disk with `DEMO_PHOTO_LIBRARY_DISK`):

```text
storage/app/demo/faces/
    male/      # *.jpg *.jpeg *.png *.webp (recursive)
    female/    # *.jpg *.jpeg *.png *.webp (recursive)
```

Behavior:

- Gender-aware: male users draw from `male/`, female from `female/`,
  unknown gender from the whole library.
- Deterministic: same demo user + photo index always resolves to the same
  file (hash offset into the sorted file list), so reruns are stable.
- Consecutive users never receive the identical photo when the library
  has more than one image in the pool.
- Every pick is resized/cropped to the 480x600 profile JPEG format with
  Intervention Image (already a project dependency); output rows keep
  `status=approved`, primary flag, schema, and disk behavior identical
  to generated avatars.
- Insufficient/empty library: that slot falls back to the `generated`
  driver instead of failing the seed; the command reports
  `Fallback photos: N`.

Example output:

```text
Demo users: 5000
Photo library: local_library
Male photos: 2500
Female photos: 2500
Users processed: 5000
Photos generated: 7500
Fallback photos: 0
```

### How many photos are required for 5,000 users?

`DEMO_PHOTOS_PER_USER=1.5` → ~7,500 photo slots. Because selection is
hash-spread (not 1:1), aim for **at least ~500–1,000 unique faces per
gender** for natural variety; fewer still works (selection cycles) but
repeats become visible. Zero images also works (100% generated fallback).

### Licensing requirement (operator responsibility)

The package ships **no faces**. You must supply the library yourself and
may only use images whose license permits commercial demo and
source-code distribution. Recommended: generate them locally with an
open image model (e.g. Stable Diffusion/SDXL with a Southeast Asian
portrait prompt set), or a synthetic-face dataset with an explicit
commercial-use grant. Do NOT use: real people's photos, screenshots,
social-media scrapes, or stock-photo sites (their licenses forbid
redistribution inside a sold source package).

## Measured performance (dev MySQL 8, 5000 users)

```text
Seed duration : ~351s  (photos ~60%: 7500 GD renders; rest bulk inserts)
DB size       : ~93 MB
Storage       : ~99 MB (7500 avatars @480×600 q82)
Memory        : < 512 MB (chunked bulk inserts, no per-user queries)
Discover p1+p2: ~4s for 2×20 scored pages on first hit (warms MatchScore),
                subsequent hits reuse fresh scores
```

MySQL: default InnoDB settings suffice for seeding; for production traffic add
`innodb_buffer_pool_size ≥ 1G`. Redis: recommended for queue/cache/Reverb in
production; demo seeding itself needs no queue. Scheduler must run
(`GenerateDailyMatches`, expiries) — see DEPLOY.md.

## Troubleshooting

- `existing demo users … --force`: a previous run is still present; reset or force.
- Slow photos: use `--no-photos` first, then `jodohku:demo:photos` separately.
- MySQL `max_allowed_packet`: bulk chunks are 500 rows; lower `--users` per run if needed.
- `Vite manifest not found` on fresh installs: run `npm install && npm run build`.
