# SOCIAL ARCHITECTURE

## Social graph

- `follows(follower_id, followed_id)` unique pair + kedua arah index.
- `mutes(muter_id, muted_id)` — menyembunyikan konten, bukan relasi blokir.
- `Block::existsBetween` tetap otoritas safety (guard di FollowService,
  ReactionService, policies).
- `Follow::mutual()` untuk badge/lingkaran sosial.
- Suggested people: minat + mutual follows + grup + kota, cache/hari,
  tidak pernah blocked/muted/incognito/counselor.

## Content

- `posts`: visibility enum, `group_id` (komunitas), `media_paths`,
  counters (`likes/comments/shares_count`), `boosted_until`, soft deletes.
- `comments`: `parent_id` replies, edit/hapus (author + post author + staff).
- `post_reactions`/`comment_reactions`/`story_reactions`: kolom `type`
  string — tipe baru = tambah konstanta, tanpa migrasi.
- `post_bookmarks` privat per user; `post_shares` dengan kutipan opsional.
- `hashtags` + `post_hashtag` (counter denormalized, trending cache 1 jam).
- `mentions` morph (post/comment) + notifikasi sekali per baris.

## Stories

`stories(user, type, media_path, body, visibility, expires_at, counters)`.
Aktif = `expires_at > now()` (scope + index). Grace 7 hari lalu prune
(`PruneStaleData`, cascade views/reactions). Viewers list = Premium
(seperti visitors). Reaksi 6 tipe. Upload: validasi sync, optimasi queue
(`ProcessMediaUpload`, intervention, best-effort).

## Communities (Group)

Roles: owner (`owner_id`) + `admin`/`moderator`/`member` (rows).
`isManager()` = owner/admin/moderator. Join: public/members_only;
private = invite via moderator (tambah member langsung). Postingan grup
= `Post.group_id` + feed grup memakai `visibleTo` yang sama.
`members_count`/`posts_count` denormalized (dirawat di join/leave/seeder).

## Events

RSVP = `EventMember.status`: `confirmed`/`maybe`/`declined`
(`updateOrCreate`, idempotent). Join/leave + seats/Capacity existing
dipertahankan. Analytics `event_rsvp` membawa value status.
