<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Services\PseoService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public SEO/PSEO surfaces. Everything here is guest-accessible and must
 * stay free of private member data: only aggregate counts, curated copy,
 * and explicitly opt-in public profiles.
 */
class PublicSeoController extends Controller
{
    public function __construct(protected PseoService $pseo, protected SeoService $seo) {}

    protected function schemasEnabled(): bool
    {
        return (bool) $this->seo->site('schema_enabled', true);
    }

    // ---------- PSEO hub ----------

    public function hub()
    {
        $cities = $this->pseo->indexableCities();
        $topics = $this->pseo->topics();
        $base = rtrim((string) config('app.url', url('/')), '/');
        $schemas = [];
        if ($this->schemasEnabled()) {
            $schemas[] = $this->seo->collectionSchema('Biro Jodoh Indonesia — '.$this->seo->siteName(), $base.'/biro-jodoh', 'Direktori biro jodoh per kota dan panduan taaruf.');
            $schemas[] = $this->seo->breadcrumbSchema($this->pseo->crumbs([['name' => 'Biro Jodoh', 'url' => $base.'/biro-jodoh']]));
            $schemas[] = $this->seo->itemListSchema('Biro jodoh per kota', array_map(fn ($slug, $c) => ['name' => 'Biro Jodoh '.$c['name'], 'url' => $base.'/biro-jodoh/'.$slug], array_keys($cities), $cities));
        }

        return response()->view('pseo.hub', [
            'cities' => $cities,
            'counts' => $this->pseo->cityCounts(),
            'topics' => $topics,
            'totalMembers' => $this->totalMembers(),
            'seoSchemas' => $schemas,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function city(string $city)
    {
        $slug = mb_strtolower($city);
        $cities = $this->pseo->cities();
        if (! $this->pseo->enabled() || ! isset($cities[$slug])) {
            abort(404);
        }
        $data = $cities[$slug];
        $count = $this->pseo->cityCount($data['name']);
        // Quality gate: thin pages are never generated.
        if ($count < $this->pseo->minMembers()) {
            abort(404);
        }
        $base = rtrim((string) config('app.url', url('/')), '/');
        $nearby = array_filter(array_map(fn ($s) => isset($cities[$s]) ? ['slug' => $s] + $cities[$s] : null, $data['nearby']));
        $topics = array_slice($this->pseo->topics(), 0, 3, true);
        $faqs = [
            ['q' => 'Apakah ada member '.$data['name'].' yang serius menikah?', 'a' => 'Ya — tercatat '.$count.' member aktif di '.$data['name'].' dan sekitarnya. Filter tujuan hubungan "menikah" untuk menemukan mereka.'],
            ['q' => 'Bagaimana cara bertemu member '.$data['name'].'?', 'a' => 'Daftar gratis, lengkapi profil, gunakan filter kota, dan ikuti event komunitas di wilayah '.$data['province'].'.'],
            ['q' => 'Apakah data member '.$data['name'].' ditampilkan di sini?', 'a' => 'Tidak. Halaman ini hanya memuat informasi umum kota; profil member bersifat privat kecuali pemiliknya memilih publik.'],
        ];
        $schemas = [];
        if ($this->schemasEnabled()) {
            $schemas[] = $this->seo->breadcrumbSchema($this->pseo->crumbs([
                ['name' => 'Biro Jodoh', 'url' => $base.'/biro-jodoh'],
                ['name' => 'Biro Jodoh '.$data['name'], 'url' => $base.'/biro-jodoh/'.$slug],
            ]));
            $schemas[] = $this->seo->faqSchema($faqs);
        }

        return response()->view('pseo.location', [
            'slug' => $slug, 'city' => $data, 'count' => $count,
            'nearby' => $nearby, 'topics' => $topics, 'faqs' => $faqs,
            'totalMembers' => $this->totalMembers(),
            'seoSchemas' => $schemas,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function taaruf()
    {
        $base = rtrim((string) config('app.url', url('/')), '/');
        $topics = $this->pseo->topics();
        $faqs = [
            ['q' => 'Apa itu Smart Taaruf di Jodohku?', 'a' => 'Alur taaruf terpandu: kenalan → taaruf → khitbah, dilengkapi topik diskusi, saran AI, wali/chaperone, dan konselor bila dibutuhkan.'],
            ['q' => 'Apakah taaruf harus melibatkan orang tua?', 'a' => 'Sangat dianjurkan. Jodohku mendukung persetujuan wali dan kehadiran chaperone pihak ketiga sejak tahap taaruf.'],
            ['q' => 'Apakah AI yang menentukan jodoh saya?', 'a' => 'Tidak. AI hanya asisten (topik, saran balasan, ringkasan); skor kompatibilitas dihitung transparan dari data profil dan kuesioner.'],
            ['q' => 'Berapa biaya mengikuti taaruf?', 'a' => 'Akun gratis sudah bisa match dan chat. Fitur premium (filter lanjutan, boost) bersifat opsional.'],
        ];
        $schemas = [];
        if ($this->schemasEnabled()) {
            $schemas[] = $this->seo->breadcrumbSchema($this->pseo->crumbs([['name' => 'Taaruf', 'url' => $base.'/taaruf']]));
            $schemas[] = $this->seo->faqSchema($faqs);
        }

        return response()->view('pseo.taaruf', [
            'topics' => $topics, 'faqs' => $faqs,
            'cities' => array_slice($this->pseo->indexableCities(), 0, 6, true),
            'seoSchemas' => $schemas,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function topic(string $topic)
    {
        $topics = $this->pseo->topics();
        if (! $this->pseo->enabled() || ! isset($topics[$topic])) {
            abort(404);
        }
        $data = $topics[$topic];
        $base = rtrim((string) config('app.url', url('/')), '/');
        $others = array_filter($topics, fn ($_, $s) => $s !== $topic, ARRAY_FILTER_USE_BOTH);
        $others = array_slice($others, 0, 3, true);
        $schemas = [];
        if ($this->schemasEnabled()) {
            $schemas[] = $this->seo->articleSchema($data['title'], $base.'/panduan/'.$topic, $data['desc'], now()->subDays(30)->toAtomString(), now()->toAtomString());
            $schemas[] = $this->seo->breadcrumbSchema($this->pseo->crumbs([
                ['name' => 'Taaruf', 'url' => $base.'/taaruf'],
                ['name' => $data['title'], 'url' => $base.'/panduan/'.$topic],
            ]));
            $schemas[] = $this->seo->faqSchema($data['faqs']);
        }

        return response()->view('pseo.topic', [
            'slug' => $topic, 'topic' => $data, 'others' => $others,
            'cities' => array_slice($this->pseo->indexableCities(), 0, 5, true),
            'seoSchemas' => $schemas,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    // ---------- opt-in public profile ----------

    public function profile(string $username)
    {
        $user = User::where('username', $username)->with(['profile', 'interests'])->first();
        if (! $user || ! $this->publicEligible($user)) {
            abort(404);
        }
        $privacy = $user->profilePrivacy;
        $profile = $user->profile;
        $photo = ($privacy?->photos_visibility ?? 'public') === 'public' ? $user->avatarUrl() : null;
        $showAge = ($privacy?->age_visibility ?? 'public') === 'public';
        $showBio = ($privacy?->bio_visibility ?? 'public') === 'public';
        $showCity = in_array($privacy?->location_visibility, ['public'], true);
        $base = rtrim((string) config('app.url', url('/')), '/');
        $schemas = [];
        if ($this->schemasEnabled()) {
            $schemas[] = $this->seo->profileSchema($user->displayName(), $base.'/u/'.$user->username, $showCity ? $user->city : null, $photo);
            $schemas[] = $this->seo->breadcrumbSchema($this->pseo->crumbs([['name' => $user->displayName(), 'url' => $base.'/u/'.$user->username]]));
        }

        return response()->view('pseo.profile', [
            'user' => $user, 'profile' => $profile, 'photo' => $photo,
            'showAge' => $showAge, 'showBio' => $showBio, 'showCity' => $showCity,
            'seoSchemas' => $schemas,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    protected function publicEligible(User $user): bool
    {
        if (($user->status?->value ?? 'active') !== 'active') {
            return false;
        }
        if ($user->trashed()) {
            return false;
        }
        $privacy = $user->profilePrivacy;
        if (! $privacy || ! (bool) ($privacy->is_public_index ?? false)) {
            return false;
        }
        if ((bool) ($privacy->is_incognito ?? false)) {
            return false;
        }

        return true;
    }

    protected function totalMembers(): int
    {
        return (int) Cache::remember('seo:total-members', 3600, fn () => User::active()->count());
    }

    // ---------- sitemap ----------

    public function sitemapIndex(Request $request)
    {
        if (! $this->seo->site('sitemap_enabled', true)) {
            abort(404);
        }
        $sections = Cache::remember('seo:sitemap', 3600, fn () => $this->sitemapSections());

        return response()->view('seo.sitemap-index', ['sections' => $sections])
            ->header('Content-Type', 'application/xml');
    }

    /** @return array<int, array{loc:string,updated:string}> */
    protected function sitemapSections(): array
    {
        $base = rtrim((string) config('app.url', url('/')), '/');
        $now = now()->toAtomString();
        $out = [['loc' => $base.'/sitemap-pages.xml', 'updated' => $now]];
        if ($this->pseo->indexableCities()) {
            $out[] = ['loc' => $base.'/sitemap-locations.xml', 'updated' => $now];
        }
        if ($this->pseo->enabled()) {
            $out[] = ['loc' => $base.'/sitemap-pseo.xml', 'updated' => $now];
        }
        if ($this->publicProfilesQuery()->exists()) {
            $out[] = ['loc' => $base.'/sitemap-profiles.xml', 'updated' => $now];
        }
        if (Group::where('visibility', 'public')->exists()) {
            $out[] = ['loc' => $base.'/sitemap-groups.xml', 'updated' => $now];
        }

        return $out;
    }

    public function sitemapSection(string $section)
    {
        if (! $this->seo->site('sitemap_enabled', true)) {
            abort(404);
        }
        abort_if(! in_array($section, ['pages', 'locations', 'pseo', 'profiles', 'groups'], true), 404);
        $base = rtrim((string) config('app.url', url('/')), '/');
        // P1: section bodies were rebuilt per request; cache 1h like the index.
        $urls = Cache::remember('seo:sitemap:'.$section, 3600, fn () => match ($section) {
            'pages' => $this->sitemapPages($base),
            'locations' => $this->sitemapLocations($base),
            'pseo' => $this->sitemapTopics($base),
            'profiles' => $this->sitemapProfiles($base),
            'groups' => $this->sitemapGroups($base),
        });

        return response()->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    protected function sitemapPages(string $base): array
    {
        $now = now()->toAtomString();
        $paths = ['/', '/biro-jodoh', '/taaruf', '/guidelines', '/privacy', '/terms', '/contact'];
        $urls = [];
        foreach ($paths as $p) {
            $urls[] = ['loc' => $base.$p, 'updated' => $now, 'freq' => $p === '/' ? 'daily' : 'weekly'];
        }
        foreach (array_keys($this->pseo->topics()) as $slug) {
            $urls[] = ['loc' => $base.'/panduan/'.$slug, 'updated' => $now, 'freq' => 'weekly'];
        }

        return $urls;
    }

    protected function sitemapLocations(string $base): array
    {
        $now = now()->toAtomString();
        $urls = [];
        foreach ($this->pseo->indexableCities() as $slug => $c) {
            $urls[] = ['loc' => $base.'/biro-jodoh/'.$slug, 'updated' => $now, 'freq' => 'weekly'];
        }

        return $urls;
    }

    protected function sitemapTopics(string $base): array
    {
        // Alias section kept for crawler compatibility; same set as pages.
        return array_values(array_filter(
            $this->sitemapPages($base),
            fn ($u) => str_contains($u['loc'], '/panduan/') || str_contains($u['loc'], '/taaruf')
        ));
    }

    protected function sitemapProfiles(string $base): array
    {
        $rows = $this->publicProfilesQuery()->orderByDesc('updated_at')->limit(5000)->get(['username', 'updated_at']);

        return $rows->map(fn ($u) => [
            'loc' => $base.'/u/'.$u->username,
            'updated' => $u->updated_at?->toAtomString() ?? now()->toAtomString(),
            'freq' => 'weekly',
        ])->all();
    }

    /** Public groups only — private/hidden never indexed. */
    protected function sitemapGroups(string $base): array
    {
        $rows = Group::where('visibility', 'public')->orderByDesc('updated_at')->limit(1000)->get(['slug', 'updated_at']);

        return $rows->map(fn ($g) => [
            'loc' => $base.'/g/'.$g->slug,
            'updated' => $g->updated_at?->toAtomString() ?? now()->toAtomString(),
            'freq' => 'weekly',
        ])->all();
    }

    protected function publicProfilesQuery()
    {
        return User::active()->whereNotNull('username')
            ->whereHas('profilePrivacy', fn ($q) => $q->where('is_public_index', true)->where('is_incognito', '!=', true));
    }

    // ---------- robots ----------

    public function robots()
    {
        $base = rtrim((string) config('app.url', url('/')), '/');
        $lines = [
            'User-agent: *',
            'Allow: /$',
            'Allow: /biro-jodoh',
            'Allow: /biro-jodoh/*',
            'Allow: /taaruf',
            'Allow: /panduan/*',
            'Allow: /u/*',
            'Allow: /g/*',
            'Allow: /guidelines',
            'Allow: /privacy',
            'Allow: /terms',
            'Allow: /contact',
            'Disallow: /home',
            'Disallow: /discover',
            'Disallow: /matches',
            'Disallow: /likes',
            'Disallow: /chat',
            'Disallow: /notifications',
            'Disallow: /settings',
            'Disallow: /premium',
            'Disallow: /credits',
            'Disallow: /verification',
            'Disallow: /groups',
            'Disallow: /stories',
            'Disallow: /cari',
            'Disallow: /suggested',
            'Disallow: /pengikut',
            'Disallow: /mengikuti',
            'Disallow: /biro-jodoh/taaruf',
            'Disallow: /biro-jodoh/konsultasi',
            'Disallow: /biro-jodoh/laporan',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /*?',
            '',
            "Sitemap: {$base}/sitemap.xml",
        ];

        return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain');
    }
}
