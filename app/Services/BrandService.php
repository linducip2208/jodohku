<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Whitelabel brand resolution + asset URLs with Jodohku defaults.
 *
 * Order: request domain → default brand → built-in Jodohku fallback.
 * Resolved brand is cached 1h (busted on admin save).
 */
class BrandService
{
    public const FALLBACK = [
        'name' => 'Jodohku',
        'tagline' => 'Temukan pasangan yang sejalan nilai dan tujuan pernikahan.',
        'primary' => '#f43f5e',
        'secondary' => '#8b5cf6',
    ];

    public function current(?string $host = null): ?Brand
    {
        $host ??= request()->getHost();
        $key = 'brand:host:'.strtolower((string) $host);

        return Cache::remember($key, 3600, function () use ($host) {
            $byDomain = Brand::where('is_active', true)->where('domain', $host)->first();
            if ($byDomain) {
                return $byDomain;
            }

            return Brand::where('is_active', true)->where('is_default', true)->first();
        });
    }

    /** @return array{name:string,tagline:string,primary:string,secondary:string,logo:?string,favicon:?string,slug:?string,features:array} */
    public function theme(?Brand $brand = null): array
    {
        $brand ??= $this->current();

        return [
            'slug' => $brand?->slug,
            'name' => $brand?->name ?? self::FALLBACK['name'],
            'tagline' => $brand?->tagline ?? self::FALLBACK['tagline'],
            'primary' => $this->sanitizeHex($brand?->primary_color) ?? self::FALLBACK['primary'],
            'secondary' => $this->sanitizeHex($brand?->secondary_color) ?? self::FALLBACK['secondary'],
            'logo' => $brand?->logoUrl(),
            'favicon' => $brand?->faviconUrl(),
            'features' => $brand?->features ?? [],
        ];
    }

    /** Per-brand feature flag (empty = follow global default). */
    public function featureOn(?Brand $brand, string $key, bool $default = true): bool
    {
        $brand ??= $this->current();

        return $brand ? $brand->featureOn($key, $default) : $default;
    }

    public function sanitizeHex(?string $hex): ?string
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return strtolower($hex);
    }

    public function forgetCache(?Brand $brand = null): void
    {
        try {
            if ($brand?->domain) {
                Cache::forget('brand:host:'.strtolower($brand->domain));
            }
            Cache::forget('brand:host:'.strtolower((string) request()->getHost()));
        } catch (\Throwable) {
        }
    }

    /**
     * Export brand package (brand.json + assets) as zip binary.
     * Import reverses it (see importPackage).
     */
    public function exportPackage(Brand $brand): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'brand').'.zip';
        $zip = new \ZipArchive;
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create brand package.');
        }
        $zip->addFromString('brand.json', (string) json_encode([
            'slug' => $brand->slug, 'name' => $brand->name, 'tagline' => $brand->tagline,
            'primary_color' => $brand->primary_color, 'secondary_color' => $brand->secondary_color,
            'domain' => $brand->domain, 'features' => $brand->features, 'footer' => $brand->footer,
            'exported_at' => now()->toIso8601String(), 'app' => 'jodohku-whitelabel-v1',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        foreach (['logo_path' => 'logo', 'favicon_path' => 'favicon'] as $attr => $name) {
            $path = $brand->$attr;
            if ($path && Storage::disk('public')->exists($path)) {
                $zip->addFromString('assets/'.$name.'.'.pathinfo($path, PATHINFO_EXTENSION), Storage::disk('public')->get($path));
            }
        }
        $zip->close();

        return $tmp;
    }

    /** Import a package zip. Returns the created/updated Brand. */
    public function importPackage(string $zipPath, bool $activate = false): Brand
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Paket brand tidak valid.');
        }
        $json = $zip->getFromName('brand.json');
        if (! $json) {
            throw new \RuntimeException('brand.json hilang dari paket.');
        }
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['app'] ?? null) !== 'jodohku-whitelabel-v1' || empty($data['slug']) || empty($data['name'])) {
            throw new \RuntimeException('Paket brand rusak.');
        }
        $brand = Brand::firstOrNew(['slug' => $data['slug']]);
        $brand->fill([
            'name' => mb_substr((string) $data['name'], 0, 80),
            'tagline' => isset($data['tagline']) ? mb_substr((string) $data['tagline'], 0, 200) : null,
            'primary_color' => $this->sanitizeHex($data['primary_color'] ?? null) ?? self::FALLBACK['primary'],
            'secondary_color' => $this->sanitizeHex($data['secondary_color'] ?? null) ?? self::FALLBACK['secondary'],
            'domain' => $data['domain'] ?? null,
            'features' => is_array($data['features'] ?? null) ? $data['features'] : null,
            'footer' => is_array($data['footer'] ?? null) ? $data['footer'] : null,
        ]);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! str_starts_with($name, 'assets/')) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if ($content === false || strlen($content) > 5 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'], true)) {
                continue;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($content);
            if (! str_starts_with((string) $mime, 'image/')) {
                continue;
            }
            $stored = 'brands/'.$brand->slug.'/'.basename($name);
            Storage::disk('public')->put($stored, $content);
            if (str_contains($name, 'logo')) {
                $brand->logo_path = $stored;
            } else {
                $brand->favicon_path = $stored;
            }
        }
        $zip->close();
        if ($activate) {
            Brand::where('is_default', true)->update(['is_default' => false]);
            $brand->is_default = true;
            $brand->is_active = true;
        }
        $brand->save();
        $this->forgetCache($brand);

        return $brand->fresh();
    }
}
