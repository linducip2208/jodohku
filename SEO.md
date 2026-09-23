# SEO — Jodohku

Central service: `App\Services\SeoService` + `<x-seo>` Blade component.
Config: `config/seo.php` (env `SEO_*`) overridden per-install by the Settings
`seo` group (superadmin UI). No second SEO system.

## Indexing map

```text
PUBLIC INDEXABLE   /  /biro-jodoh  /biro-jodoh/{city}*  /taaruf  /panduan/{topic}
                   /u/{username}*  /guidelines /privacy /terms /contact
PUBLIC NON-INDEX   /login /register /forgot-password /reset-password /2fa/*
                   /verify-email/*  error pages (robots noindex)
AUTHENTICATED      everything under auth (member layout forces noindex+nofollow)
ADMIN / API        auth-gated + robots Disallow
* gated: city needs ≥ SEO_PSEO_MIN_MEMBERS active members; profile needs
  explicit opt-in (profile_privacy.is_public_index) + active + non-incognito
```

## Mechanics

- Canonical (`SeoService::canonical`): absolute via `APP_URL`, strips
  `utm_*`/`gclid`/`fbclid`/`msclkid`/`ref`/`source`, normalizes trailing slash.
- Trailing slash: `NormalizeTrailingSlash` middleware 301s GET/HEAD duplicates.
- Discover filters live behind auth and are `noindex` — only curated PSEO
  taxonomy pages are crawlable (no infinite filter combinations).
- Schemas (behind `schema_enabled`): WebSite, Organization, BreadcrumbList,
  BlogPosting, Event, FAQPage, CollectionPage, ProfilePage, ItemList — public
  data only, validated as JSON in `SeoGeoPseoTest`.
- Verification metas (Google/Bing) render only when the settings keys are set;
  no tracking IDs are hardcoded.
- Images (public): descriptive `alt`, explicit `width`/`height`, `loading=lazy`.
- Cache: sitemap + city counts cached 1h (`seo:*`); busted on admin settings
  save. Never cache personalized content.
- Sitemap: `/sitemap.xml` index → `sitemap-{pages,locations,pseo,profiles}.xml`.
  Only canonical, public, indexable, 200-OK URLs. Blog/forum/event/member URLs
  are intentionally excluded (auth-gated).

## White-label

Change brand/domain/title/description/logo/OG/locale/robots/sitemap/PSEO
thresholds via env (`SEO_*`) or Admin → Settings (`seo` group) — no code edits.

## Search Console / Bing

1. Set `seo.google_site_verification` / `seo.bing_site_verification` in Admin Settings.
2. Submit `https://YOUR-DOMAIN/sitemap.xml` in both consoles.
3. Serve HTTPS + `www` canonicalization at the web server / CDN level.
