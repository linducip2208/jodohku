# GEO — Generative Engine Optimization (Jodohku)

Goal: public pages that search engines **and** AI/answer engines can parse
accurately — without leaking private member data or gaming models.

## Principles

- Facts first: real aggregate counts, real dates, consistent brand/service facts.
- One visible source: `FaqService` feeds both the rendered FAQ lists and the
  `FAQPage` JSON-LD — never hidden crawler-only content.
- Semantic HTML: single H1, logical H2/H3 hierarchy, `<article>` for guides.
- Freshness: `datePublished`/`dateModified` from real timestamps; sitemap
  `lastmod` from actual `updated_at`.

## Content pattern (PSEO + guides)

```text
H1 (specific) → short answer/intro → key points → details → visible FAQ
→ related links → breadcrumb
```

## Entity consistency (keep identical everywhere)

brand=Jodohku · service=biro jodoh modern · country=Indonesia · language=id ·
features=(matchmaking, Smart Taaruf, chat aman, konselor, komunitas) ·
support=via /contact · safety via /guidelines + /safety.

## Never exposed

Private profiles, messages, photos, emails, phones, precise locations,
matching preferences, questionnaire answers, payments, moderation internals.
PSEO pages show **aggregate counts only**; public profiles show only fields
whose visibility setting is `public` (guests are treated as non-members).
