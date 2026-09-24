# DISCOVERY SCALABILITY — Jodohku (P0 implementation)

`MatchingEngine` is untouched as the scoring authority (same 8 dims, same
weights, same `scorePair`, same `explain`). What changed is HOW candidates
reach it.

## Pipeline

```text
User Request
  ↓  CandidateRetrievalService::pool() — ONE canonical filter stack
Hard filters (indexed queries: status/self, gender, city, age bounds,
verified/online/premium/photo/keyword/goal/height/marital/religion,
distance bbox, blocked exclusion, incognito rule, exclude_ids)
  ↓  pool pages (cursor, unique id tiebreaker)
Existing MatchingEngine::scorePair/scoreMany (unchanged semantics)
  ↓  MatchScore read-path (fresh rows reused, misses upserted)
Behavioral ranking (boost reorders only — never rescores)
  ↓  PersonalizationService composes results
Final Results (ScoredCursorPaginator, leftover-carry, no dupes)
```

## What was duplicate (removed)

`DiscoveryService::poolQuery()` and `MatchingEngine::candidatesFor()` each
built the same ~20-clause filter stack. Both now delegate to
`CandidateRetrievalService::pool()`:

- `discover()` → `pool($user, $filters, $sort, ['excludeLiked' => true])`
  (liked hidden until rewound; NO preference defaults — explicit filters win).
- `candidatesFor()` → `pool($user, $filters, null, ['preferenceDefaults' => true])`
  (gender/age defaults from `partnerPreference`; likes never excluded).

## Intentional semantic notes (documented, not accidental)

1. Distance now requires both coordinates (was: latitude-only in discovery).
   Rationale: lat-without-lng is corrupt data; the old behavior returned an
   empty page, the new one ignores the broken filter.
2. Reports still do NOT exclude anyone from retrieval (only blocks gate) —
   preserved behavior, covered by test.
3. `passesHardFilter()` (single-pair, always fresh) is kept for endpoints;
   hot loops use `passesHardFilterFast()` + `blockedIdsFor()` memo.

## Performance measures (no new infra)

- In-request memos are **instance properties** (`weightsMemo` version-keyed,
  `blockedIdsMemo`), never function-statics: queue workers are long-lived
  and must not serve stale blocks/weights to later jobs. `Setting::get`
  keeps its static cache but now also caches misses.
- Measured on 5,003-user MySQL: discover 163ms/12q, dailyPicks 384ms/32q.

## Indexes (migration `2026_09_24_000001_add_scale_indexes`)

notifications morph+time ×2 · matches per-side active ×2 · users geo/flags ·
audit_logs time. All additive; online DDL; rollback = drop index.

## Horizontal scaling

Retrieval is stateless (request + filters in, Builder out). Web nodes scale
horizontally with zero coordination; score cache (`match_scores` + TTL) and
versioned weights are shared via MySQL. No sticky sessions needed.

## Tests

`tests/Feature/CandidateRetrievalTest.php`: age/gender/city/goal/distance/
verified/privacy/blocked-both-ways/incognito+liker/reports-preserved/
excludeLiked/exclude_ids/preference-defaults/empty/inactive/stale-recompute/
cache-reuse/cold-start/weights-version. Pagination covered by
`DiscoveryPaginationScoreTest`.

## P1–P2 follow-ups (implemented)

- **Liquidity backfill** (`DiscoveryService::backfillByRadius`): halaman
  pertama yang pendek + filter `max_distance_km` dicoba ulang 2x/3x radius
  (cap = default matchmaking/50km) dengan `exclude_ids` = sudah terlihat.
  Skoring, hard-filter, dan ranking boost-only sama; cursor tak tersentuh
  karena hanya jalan saat `$cursor === null`. Test:
  `ScaleHardeningTest::test_discovery_backfills_wider_radius_on_thin_pool`
  (Jakarta 50km → Bandung ±120km ikut lewat backfill).
- **Warming**: `UserRegistered` → `WarmNewUserMatches` → `RecalculateMatches`
  async (20 kandidat). Cold start tetap tanpa error, picks hangat.
- **Retention**: `PruneStaleData` 03:30 harian (chunk 1k, time-bounded).
- **Analytics**: `AnalyticsController` 60–300s per endpoint; sitemap sections
  `seo:sitemap:{section}` 1h; PSEO `seo:pseo:cities` 1h + header
  `Cache-Control: public, max-age=3600` tetap.
