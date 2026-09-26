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

    /** One-click sales templates for the wizard gallery. */
    public const TEMPLATES = [
        'jodohku' => ['name' => 'Jodohku', 'tagline' => 'Temukan pasangan yang sejalan nilai dan tujuan pernikahan.', 'primary' => '#f43f5e', 'secondary' => '#8b5cf6'],
        'islami' => ['name' => 'TaarufKu', 'tagline' => 'Menuju pernikahan berkah secara syari.', 'primary' => '#047857', 'secondary' => '#d4af37'],
        'premium' => ['name' => 'EliteMatch', 'tagline' => 'Matchmaking premium untuk profesional.', 'primary' => '#b45309', 'secondary' => '#111827'],
        'playful' => ['name' => 'KitaKita', 'tagline' => 'Kenalan seru, match santai.', 'primary' => '#ec4899', 'secondary' => '#8b5cf6'],
        'navy' => ['name' => 'Serasi', 'tagline' => 'Serius mencari, santun berkenalan.', 'primary' => '#1e3a8a', 'secondary' => '#0ea5e9'],
    ];

    public function current(?string $host = null): ?Brand
    {
        $host ??= request()->getHost();
        $key = 'brand:host:'.strtolower((string) $host);

        return Cache::remember($key, 3600, function () use ($host) {
            $byDomain = Brand::where('is_active', true)->where('domain', $host)->first();
            if ($byDomain && $byDomain->licensed()) {
                return $byDomain;
            }
            $default = Brand::where('is_active', true)->where('is_default', true)->first();

            return $default && $default->licensed() ? $default : null;
        });
    }

    /** @return array{name:string,tagline:string,primary:string,secondary:string,logo:?string,favicon:?string,slug:?string,features:array,content:array,expires_at:?string} */
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
            'content' => $brand?->content ?? [],
            'expires_at' => $brand?->expires_at?->toIso8601String(),
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
     * Generate per-brand PWA icons (192/512/maskable) into
     * brands/{slug}/icons/ using GD. Primary→secondary gradient tile
     * with the uploaded logo composited center (SVG logos fall back to
     * gradient + initial letter). Returns relative paths.
     *
     * @return array<string,string>
     */
    public function generateIcons(Brand $brand): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return [];
        }
        $base = 'brands/'.$brand->slug.'/icons';
        Storage::disk('public')->makeDirectory($base);
        $disk = Storage::disk('public');
        $logoImg = null;
        $logoPath = $brand->logo_path && $disk->exists($brand->logo_path)
            ? $disk->path($brand->logo_path) : null;
        if ($logoPath) {
            $raw = @file_get_contents($logoPath);
            if ($raw !== false) {
                $logoImg = @imagecreatefromstring($raw);
            }
        }
        [$r1, $g1, $b1] = $this->hexToRgb($brand->primary_color);
        [$r2, $g2, $b2] = $this->hexToRgb($brand->secondary_color);
        $out = [];
        foreach ([192 => 'icon-192.png', 512 => 'icon-512.png'] as $size => $file) {
            $img = imagecreatetruecolor($size, $size);
            for ($y = 0; $y < $size; $y++) {
                $t = $y / max(1, $size - 1);
                imagefilledrectangle($img, 0, $y, $size, $y, imagecolorallocate($img,
                    (int) ($r1 + ($r2 - $r1) * $t),
                    (int) ($g1 + ($g2 - $g1) * $t),
                    (int) ($b1 + ($b2 - $b1) * $t)));
            }
            if ($logoImg) {
                $lw = imagesx($logoImg);
                $lh = imagesy($logoImg);
                $target = (int) ($size * 0.6);
                $scale = min($target / max(1, $lw), $target / max(1, $lh));
                $dw = max(1, (int) ($lw * $scale));
                $dh = max(1, (int) ($lh * $scale));
                imagecopyresampled($img, $logoImg, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $lw, $lh);
            } else {
                $white = imagecolorallocate($img, 255, 255, 255);
                $cx = (int) ($size / 2);
                $d = (int) ($size * 0.52);
                imagefilledellipse($img, $cx, $cx, $d, $d, $white);
                $inner = imagecolorallocate($img, $r1, $g1, $b1);
                imagefilledellipse($img, $cx, $cx, (int) ($d * 0.62), (int) ($d * 0.62), $inner);
            }
            $abs = $disk->path($base.'/'.$file);
            imagepng($img, $abs);
            imagedestroy($img);
            $out[$file] = $base.'/'.$file;
        }
        if ($logoImg) {
            imagedestroy($logoImg);
        }
        if (isset($out['icon-512.png'])) {
            $disk->copy($out['icon-512.png'], $base.'/icon-maskable.png');
            $out['icon-maskable.png'] = $base.'/icon-maskable.png';
        }
        if (isset($out['icon-192.png'])) {
            $disk->copy($out['icon-192.png'], $base.'/apple-touch-icon.png');
        }

        return $out;
    }

    /** @return array{0:int,1:int,2:int} */
    protected function hexToRgb(?string $hex): array
    {
        $hex = $this->sanitizeHex($hex) ?? '#f43f5e';

        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
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
