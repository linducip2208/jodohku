<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Central SEO/GEO metadata builder (single source of truth).
 *
 * Fallback chain everywhere: page value → content value → site default
 * (Settings `seo` group first, then config/seo.php, so white-label installs
 * never touch code). Canonicals strip tracking params; JSON-LD helpers emit
 * only schema.org types that match the actual page content — never private
 * member data.
 */
class SeoService
{
    /** Query params that must never create duplicate canonical URLs. */
    protected array $trackingParams = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'msclkid', 'ref', 'source',
    ];

    public function site(string $key, mixed $default = null): mixed
    {
        // Legacy key aliases already seeded in the wild.
        $key = ['title' => 'default_title', 'description' => 'default_description'][$key] ?? $key;
        try {
            $stored = Setting::get($key, null, 'seo');
            if ($stored !== null && $stored !== '') {
                return $stored;
            }
        } catch (\Throwable) {
        }

        return config('seo.'.$key, $default);
    }

    public function siteName(): string
    {
        return (string) $this->site('site_name', config('app.name', 'Jodohku'));
    }

    public function defaultTitle(): string
    {
        return (string) $this->site('title', 'Jodohku — Biro Jodoh Modern Indonesia');
    }

    public function defaultDescription(): string
    {
        return (string) $this->site('description', '');
    }

    public function robotsIndexAllowed(): bool
    {
        if (! $this->boolish($this->site('robots_index', config('seo.robots_index', true)))) {
            return false;
        }

        return true;
    }

    protected function boolish(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Canonical URL: absolute, https-aware via app.url, tracking params
     * stripped, trailing slash normalized (except root).
     */
    public function canonical(?string $url = null): string
    {
        $url ??= url()->current();
        $parts = parse_url($url);
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach ($this->trackingParams as $t) {
                unset($query[$t]);
            }
        }
        $path = rtrim($parts['path'] ?? '/', '/');
        $path = $path === '' ? '/' : $path;
        $base = rtrim((string) config('app.url', url('/')), '/');
        $out = $base.$path;
        if ($query) {
            ksort($query);
            $out .= '?'.http_build_query($query);
        }

        return $out;
    }

    public function absoluteUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) config('app.url', url('/')), '/').'/'.ltrim($path, '/');
    }

    /** Full meta array for a page (page → content → site fallbacks). */
    public function meta(array $page = []): array
    {
        $title = trim((string) ($page['title'] ?? '')) ?: $this->defaultTitle();
        // De-dupe runaway titles while keeping brand suffix intact.
        if (mb_strlen($title) > 140) {
            $title = mb_substr($title, 0, 137).'…';
        }
        $description = trim((string) ($page['description'] ?? '')) ?: $this->defaultDescription();
        if (mb_strlen($description) > 220) {
            $description = mb_substr($description, 0, 217).'…';
        }
        $image = $this->absoluteUrl((string) ($page['image'] ?? $this->site('default_image', '/og-cover.jpg')));
        $robots = (string) ($page['robots'] ?? ($this->robotsIndexAllowed() ? 'index, follow' : 'noindex, nofollow'));

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => (string) ($page['keywords'] ?? $this->site('keywords', '')),
            'canonical' => (string) ($page['canonical'] ?? $this->canonical()),
            'robots' => $robots,
            'og_title' => (string) ($page['og_title'] ?? $title),
            'og_description' => (string) ($page['og_description'] ?? $description),
            'og_image' => $image,
            'og_url' => (string) ($page['og_url'] ?? $this->canonical()),
            'og_type' => (string) ($page['og_type'] ?? 'website'),
            'twitter_card' => 'summary_large_image',
            'twitter_title' => (string) ($page['twitter_title'] ?? $title),
            'twitter_description' => (string) ($page['twitter_description'] ?? $description),
            'twitter_image' => $image,
            'twitter_handle' => (string) $this->site('twitter_handle', ''),
            'locale' => (string) $this->site('locale', 'id_ID'),
        ];
    }

    // ---------- JSON-LD schema builders (public data only) ----------

    public function websiteSchema(?array $searchAction = null): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->siteName(),
            'url' => rtrim((string) config('app.url', url('/')), '/').'/',
            'inLanguage' => 'id',
        ];
        if ($searchAction) {
            $data['potentialAction'] = $searchAction;
        }

        return $data;
    }

    public function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->siteName(),
            'url' => rtrim((string) config('app.url', url('/')), '/').'/',
            'logo' => $this->absoluteUrl((string) $this->site('logo', '/favicon.ico')),
            'sameAs' => [],
        ];
    }

    /** @param array<int, array{name:string,url:string}> $trail */
    public function breadcrumbSchema(array $trail): array
    {
        $items = [];
        foreach (array_values($trail) as $i => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public function articleSchema(string $headline, string $url, string $description, ?string $publishedAt, ?string $updatedAt, ?string $image = null): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $headline,
            'description' => $description,
            'url' => $url,
            'inLanguage' => 'id',
            'publisher' => ['@type' => 'Organization', 'name' => $this->siteName()],
        ];
        if ($publishedAt) {
            $data['datePublished'] = $publishedAt;
        }
        if ($updatedAt) {
            $data['dateModified'] = $updatedAt;
        }
        if ($image) {
            $data['image'] = $image;
        }

        return $data;
    }

    public function eventSchema(string $name, string $url, string $start, ?string $end, string $status = 'EventScheduled', ?string $city = null): array
    {
        $location = ['@type' => 'Place', 'name' => $city ?? 'Indonesia'];
        if ($city) {
            $location['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $city, 'addressCountry' => 'ID'];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $name,
            'url' => $url,
            'startDate' => $start,
            'endDate' => $end,
            'eventStatus' => 'https://schema.org/'.$status,
            'location' => $location,
            'organizer' => ['@type' => 'Organization', 'name' => $this->siteName()],
            'inLanguage' => 'id',
        ];
    }

    /** @param array<int, array{q:string,a:string}> $faqs */
    public function faqSchema(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], array_values($faqs)),
        ];
    }

    public function collectionSchema(string $name, string $url, string $description): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'inLanguage' => 'id',
        ];
    }

    /** Public member profile only: display name, city-level locality, photo. */
    public function profileSchema(string $name, string $url, ?string $city = null, ?string $photo = null): array
    {
        $data = ['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'url' => $url, 'name' => $name.' — '.$this->siteName(), 'inLanguage' => 'id'];
        $person = ['@type' => 'Person', 'name' => $name];
        if ($city) {
            $person['homeLocation'] = ['@type' => 'City', 'name' => $city];
        }
        if ($photo) {
            $person['image'] = $photo;
        }
        $data['mainEntity'] = $person;

        return $data;
    }

    /** @param array<int, array{name:string,url:string}> $items */
    public function itemListSchema(string $name, array $items): array
    {
        $elements = [];
        foreach (array_values($items) as $i => $it) {
            $elements[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it['name'], 'url' => $it['url']];
        }

        return ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $name, 'itemListElement' => $elements];
    }

    // ---------- SEO cache (public content only, never personalized) ----------

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember('seo:'.$key, $ttl, $callback);
    }

    public function bust(string $pattern = '*'): void
    {
        // File/database drivers lack tag flushing; TTLs are short (≤1h) and
        // content writes bust the sitemap/pseo keys explicitly below.
        foreach (['seo:sitemap', 'seo:pseo:cities'] as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
            }
        }
    }
}
