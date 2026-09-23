# PSEO — Programmatic SEO (Jodohku)

Data-driven landing pages from a curated taxonomy. No copy-paste Blade farms:
two shared templates (`pseo/location`, `pseo/topic`) render all pages.

## Taxonomy

- Locations (14): `PseoService::cities()` — slug, display name, province,
  hand-written unique intro, nearby slugs.
- Topics (5): `PseoService::topics()` — title, desc, intro, key points,
  FAQs. Hub: `/taaruf`.

## Routes

`/biro-jodoh` (hub) · `/biro-jodoh/{city}` · `/taaruf` · `/panduan/{topic}` ·
`/u/{username}` (opt-in profiles, separate gate).

## Quality gate (`PublicSeoController`)

1. `pseo_enabled` setting on, slug in taxonomy — else 404.
2. Locations: active member count in that city ≥ `pseo_min_members`
   (default 10) — thin pages 404 instead of shipping noindex filler.
3. Every page: unique title/description/intro, related links, breadcrumb,
   FAQ where applicable, canonical, schema.

## Internal linking

Home → hub → city → nearby cities + topics → topic → other topics + cities.
Breadcrumbs on every page. No orphans: hub links all indexable cities/topics.

## Sitemap & cache

Sections `pages/locations/pseo/profiles` under `/sitemap.xml` (1h cache,
busted on SEO settings save). Profiles section lists only opted-in eligible
accounts (cap 5000).

## Adding a city/topic (white-label)

Edit `PseoService::cities()/topics()` with genuinely unique copy, or extend
the service to read DB-driven taxonomy. Keep the quality gate: never generate
a page for a keyword combination without real backing content.
