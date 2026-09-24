# SOCIAL DATING PLATFORM — Jodohku

Jodohku adalah **social-first dating platform**: social network + dating
engine + community + realtime messaging dalam satu modular monolith
Laravel (tanpa microservices — belum ada bottleneck yang membenarkannya).

Identitas: **SOCIAL FIRST, DATING NATIVE** — dating tidak pernah menjadi
satu-satunya fungsi; follow, feed, stories, komunitas, dan chat adalah
warga kelas satu di samping match & taaruf.

## Peta domain

| Domain | Implementasi | Status |
|---|---|---|
| Social graph | `Follow`/`Mute`, `FollowService` (suggested, cached/hari) | baru |
| Posts | `Post` (visibility, group, media, counters) + `PostObserver` (hashtag) | extend |
| Reactions | `PostReaction`/`CommentReaction`/`StoryReaction`, 6 tipe ekstensibel | baru |
| Comments | `Comment` (replies via parent_id) + edit/hapus + report | extend |
| Bookmarks/shares | `PostBookmark`/`PostShare`, counters, feed privat | baru |
| Hashtag/mention | `Hashtag`+pivot, `Mention` morph, `MentionService` + notif | baru |
| Stories | `Story` + views/reactions, TTL 24 jam via scope (tanpa cron) | baru |
| Home feed | `FeedService` (candidates→filter→score→rank→paginate) | baru |
| Dating+social | `SocialDatingRecommendationService` (skor terpisah, alasan aman) | baru |
| Search | `SearchService` (people/posts/groups/events/tags, privacy-aware) | baru |
| Communities | `Group`/`GroupMember` (roles, rules, category) + postingan grup | extend |
| Events | `Event`/`EventMember` RSVP going/maybe/declined | extend |
| Messaging | Conversation/Message + reactions/reply/status/reads/typing | existing |
| Notifikasi | DB + mail + broadcast `users.{id}` untuk tipe social | extend |
| Analytics | `AnalyticsEvent` + `AnalyticsService` (queue, tanpa PII) | baru |
| Media | `PhotoService` + `ProcessMediaUpload` (queue, intervention) | extend |
| Monetisasi | Boost profil (existing) + boost postingan (kredit, 24 jam) | extend |
| Admin | queues/reports/audit existing + `analytics/social` | extend |
| SEO | sitemap groups publik + robots | extend |

## Alur UX utama

Register → Build profile → Follow people → Explore → Stories → Post →
Comment → Join community → Discover → Like → Match → Chat → Taaruf.

## Endpoint (web + `/api/v1/social/*`, throttle per aksi)

| Domain | Web | API |
|---|---|---|
| Follow/mute/suggested | `/ikuti`, `/bisukan`, `/pengikut`, `/mengikuti`, `/suggested` | `social/follow`, `social/followers`, `social/following`, `social/suggested` |
| Feed | `/home` (FeedService), `/komunitas` | `social/feed` |
| Reactions | `/komunitas/{post}/reaksi`, komentar, story | `social/posts|comments/{id}/react` |
| Bookmark/share | `/komunitas/{post}/simpan\|bagikan`, `/komunitas/tersimpan` | via web routes (JSON-ready) |
| Comments | reply `parent_id`, edit, hapus, laporkan | via web routes (JSON-ready) |
| Stories | `/stories`, CRUD + reaksi + laporkan + viewers Premium | `social/stories` |
| Groups | `/groups`, join/leave/kelola, posting grup; publik `/g/{slug}` | `social/groups` |
| Search | `/cari` | `social/search` |
| Discover+ | `/discover?tab=orang\|postingan\|komunitas\|event\|trending` | `social/recommendations` |
| Events | RSVP going/maybe/declined di halaman event | existing JSON endpoint |
| Analytics admin | `/admin/analytics/social` | — |

## Privacy by construction

Semua visibility (`public/members_only/premium_only/matches_only/
private/hidden`) ditegakkan di **policies + query scopes**, bukan di
Blade: `PostPolicy`, `CommentPolicy`, `StoryPolicy`, `GroupPolicy`,
`Post::visibleTo`, `Group::visibleTo`, `CandidateRetrievalService`,
`FeedService`, `SearchService`, `StoryService::tray`.

## Skala

Lihat `docs/SCALE-AUDIT.md`, `docs/OPS-RUNBOOK.md`, `docs/SCALE_PLAN.md`.
