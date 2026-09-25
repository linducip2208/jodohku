<?php

namespace App\Services\Demo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Demo avatar provider with a safe licensing story.
 *
 * - `generated` (default): abstract gradient avatars rendered locally with
 *   GD. No people, no copyrighted material, no network. Safe to ship and
 *   redistribute with a commercial source-code package.
 * - `local_library` (opt-in via DEMO_PHOTO_DRIVER=local_library): picks
 *   synthetic faces from a local folder (default `demo-faces/` on the
 *   `local` disk, override with DEMO_PHOTO_LIBRARY + DEMO_PHOTO_LIBRARY_DISK),
 *   gender-aware (`male/` + `female/` subfolders), deterministic per
 *   (user,index), resized to the 480x600 profile format. The operator must
 *   supply images whose license permits commercial demo/source-code
 *   distribution (e.g. locally generated with Stable Diffusion, or a
 *   licensed synthetic-face dataset) — never real people's photos, never
 *   stock. Empty/missing library falls back to `generated` per user.
 * - `remote` (opt-in via DEMO_PHOTO_DRIVER=remote + DEMO_PHOTO_URL_TEMPLATE):
 *   downloads from a configurable URL template ({seed} placeholder), cached
 *   by URL hash, with retries + rate limiting. The operator — not this
 *   package — is responsible for the remote source's licensing terms.
 *   See DEMO.md ("Photo provider").
 */
class DemoPhotoProvider
{
    /** In-memory scan cache per library key + last pick (anti-repeat). */
    protected array $libraryCache = [];

    protected array $lastPick = [];

    /** Keys (user:index) that fell back to generated under local_library. */
    protected array $fallbacks = [];

    public function driver(): string
    {
        return strtolower((string) config('demo.photo_driver', env('DEMO_PHOTO_DRIVER', 'generated')));
    }

    /**
     * Ensure an avatar for a demo user slot. Returns the storage path
     * (relative to $disk) or null on failure. Idempotent unless $force.
     */
    public function avatar(int $userId, string $displayName, int $index = 0, string $disk = 'public', bool $force = false, ?string $gender = null): ?string
    {
        $path = "demo/avatars/u{$userId}_{$index}.jpg";
        if (! $force && Storage::disk($disk)->exists($path)) {
            return $path;
        }

        if ($this->driver() === 'local_library') {
            $local = $this->libraryAvatar($userId, $index, $disk, $path, $gender);
            if ($local) {
                return $local;
            }
            $this->fallbacks[$userId.':'.$index] = true;
            // Fall through to generated on library miss (never hard-fail seeding).
        }

        if ($this->driver() === 'remote') {
            $remote = $this->remoteAvatar($userId, $index, $disk, $force);
            if ($remote) {
                return $remote;
            }
            // Fall through to generated on remote failure (never hard-fail seeding).
        }

        return $this->generatedAvatar($userId, $displayName, $index, $disk, $path);
    }

    /**
     * Synthetic-face library pick. Deterministic per (user,index): the same
     * demo user always receives the same photo on reruns. Gender-aware via
     * male/female subfolders; unknown gender uses the whole library.
     * Returns null (caller falls back to generated) when the library is
     * missing, empty, or unreadable for the requested pool.
     */
    public function libraryAvatar(int $userId, int $index, string $disk, string $path, ?string $gender = null): ?string
    {
        $pool = $this->libraryPool($gender);
        if (empty($pool)) {
            return null;
        }
        $n = count($pool);
        // Hash-derived offset (stable across reruns, no RNG state touched).
        $pos = crc32("face:{$userId}:{$index}") % $n;
        // Avoid handing consecutive users the identical photo when the
        // library is big enough to vary (in-run memory only; reruns still
        // resolve through the same deterministic offset first).
        $cacheKey = $disk.':'.($gender ?? 'all');
        if ($n > 1 && ($this->lastPick[$cacheKey] ?? null) === $pos) {
            $pos = ($pos + 1) % $n;
        }
        $this->lastPick[$cacheKey] = $pos;

        return $this->resizeLibraryPhoto($pool[$pos]['disk'], $pool[$pos]['path'], $disk, $path);
    }

    /**
     * Scan the library into gender pools. Result: list of
     * ['disk' => ..., 'path' => ...] sorted by path (deterministic order).
     */
    public function libraryPool(?string $gender = null): array
    {
        $libDisk = (string) config('demo.photo_library_disk', env('DEMO_PHOTO_LIBRARY_DISK', 'local'));
        $libPath = trim((string) config('demo.photo_library', env('DEMO_PHOTO_LIBRARY', 'demo-faces')), '/');
        $cacheKey = $libDisk."\0".$libPath."\0".($gender ?? 'all');
        if (array_key_exists($cacheKey, $this->libraryCache)) {
            return $this->libraryCache[$cacheKey];
        }
        $pool = [];
        try {
            $disk = Storage::disk($libDisk);
            $subdirs = match (strtolower((string) $gender)) {
                'male' => ['male'],
                'female' => ['female'],
                default => ['male', 'female', ''],
            };
            foreach ($subdirs as $sub) {
                $prefix = $libPath.($sub === '' ? '' : '/'.$sub);
                if (! $disk->exists($prefix)) {
                    continue;
                }
                foreach ($disk->allFiles($prefix) as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $pool[] = ['disk' => $libDisk, 'path' => $file];
                    }
                }
            }
        } catch (\Throwable) {
            $pool = [];
        }
        usort($pool, fn ($a, $b) => strcmp($a['path'], $b['path']));
        // Deduplicate identical paths collected via overlapping subdirs.
        $pool = array_values(array_unique(array_map(fn ($e) => $e['disk']."\0".$e['path'], $pool)));
        $pool = array_map(fn ($k) => ['disk' => explode("\0", $k)[0], 'path' => explode("\0", $k)[1]], $pool);

        return $this->libraryCache[$cacheKey] = $pool;
    }

    /** Count library images per pool (for artisan output). */
    public function libraryCounts(): array
    {
        return [
            'male' => count($this->libraryPool('male')),
            'female' => count($this->libraryPool('female')),
            'all' => count($this->libraryPool(null)),
        ];
    }

    /** How many (user,index) slots fell back to generated this run. */
    public function fallbackCount(): int
    {
        return count($this->fallbacks);
    }

    /**
     * Resize/crop a library image to the 480x600 profile JPEG format.
     * Reuses Intervention Image (already a project dependency via
     * PhotoService); falls back to generated on any failure.
     */
    protected function resizeLibraryPhoto(string $libDisk, string $libPath, string $disk, string $path): ?string
    {
        try {
            if (! Storage::disk($libDisk)->exists($libPath)) {
                return null;
            }
            $bytes = Storage::disk($libDisk)->get($libPath);
            if ($bytes === '' || $bytes === null) {
                return null;
            }
            $manager = new ImageManager(new Driver);
            $image = method_exists($manager, 'decodeBinary')
                ? $manager->decodeBinary($bytes)
                : $manager->decode($bytes);
            if (method_exists($image, 'cover')) {
                $image->cover(480, 600);
            } else {
                $image->resize(480, 600);
            }
            $out = (string) $image->encode(new JpegEncoder(82));
            if ($out === '') {
                return null;
            }
            Storage::disk($disk)->put($path, $out);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Locally rendered abstract avatar. Deterministic per (user,index). */
    public function generatedAvatar(int $userId, string $displayName, int $index, string $disk, ?string $path = null): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            Log::warning('demo.photos: GD missing, skipping avatar generation.');

            return null;
        }
        $path ??= "demo/avatars/u{$userId}_{$index}.jpg";
        $w = 480;
        $h = 600;
        $seed = ($userId * 31 + $index * 101) % 360;
        $img = imagecreatetruecolor($w, $h);
        [$r1, $g1, $b1] = $this->hsl($seed, 62, 52);
        [$r2, $g2, $b2] = $this->hsl(($seed + 48) % 360, 68, 38);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            imagefilledrectangle($img, 0, $y, $w, $y, imagecolorallocate($img,
                (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
        // Soft translucent circles for texture. Hash-derived (never touches
        // the global mt_rand state, so demo seeding stays deterministic).
        $hash = hash('sha256', "demo-avatar:{$userId}:{$index}", true);
        $byte = fn ($i) => ord($hash[$i % 32]);
        for ($i = 0; $i < 7; $i++) {
            $c = imagecolorallocatealpha($img, 255, 255, 255, 95);
            imagefilledellipse(
                $img,
                $byte($i * 4) % ($w + 1),
                $byte($i * 4 + 1) % ($h + 1),
                60 + $byte($i * 4 + 2) % 161,
                60 + $byte($i * 4 + 3) % 161,
                $c
            );
        }
        // Center initial, scaled up from the GD bitmap font.
        $initial = strtoupper(mb_substr(trim($displayName) !== '' ? trim($displayName) : '?', 0, 1));
        $font = 5;
        $scale = 9;
        $cell = imagecreatetruecolor(imagefontwidth($font), imagefontheight($font));
        imagefill($cell, 0, 0, imagecolorallocate($cell, 0, 0, 0));
        imagestring($cell, $font, 0, 0, $initial, imagecolorallocate($cell, 255, 255, 255));
        $big = imagescale($cell, imagefontwidth($font) * $scale, imagefontheight($font) * $scale, IMG_NEAREST_NEIGHBOUR);
        $ox = (int) (($w - imagesx($big)) / 2);
        $oy = (int) (($h - imagesy($big)) / 2);
        imagecopy($img, $big, $ox, $oy, 0, 0, imagesx($big), imagesy($big));
        imagedestroy($cell);
        imagedestroy($big);

        ob_start();
        imagejpeg($img, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        if ($bytes === '') {
            return null;
        }
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    /** Remote download with cache-by-URL-hash, retry, and rate limiting. */
    public function remoteAvatar(int $userId, int $index, string $disk, bool $force = false): ?string
    {
        $template = (string) config('demo.photo_url_template', env('DEMO_PHOTO_URL_TEMPLATE', ''));
        if ($template === '' || ! str_contains($template, '{seed}')) {
            Log::warning('demo.photos: remote driver needs DEMO_PHOTO_URL_TEMPLATE with {seed}.');

            return null;
        }
        $url = str_replace('{seed}', "jodohku-{$userId}-{$index}", $template);
        $cachePath = 'demo/remote/'.sha1($url).'.jpg';
        if (! $force && Storage::disk($disk)->exists($cachePath)) {
            return $cachePath;
        }
        $tries = max(1, (int) config('demo.photo_retries', 2));
        for ($attempt = 1; $attempt <= $tries; $attempt++) {
            try {
                $res = Http::timeout(20)->retry(1, 500)->get($url);
                if ($res->successful() && str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                    $tmp = sys_get_temp_dir().'/demo-photo-'.$userId.'-'.$index.'.tmp';
                    file_put_contents($tmp, $res->body());
                    $info = @getimagesize($tmp);
                    if ($info && ($info[0] ?? 0) >= 100) {
                        Storage::disk($disk)->put($cachePath, file_get_contents($tmp));
                        @unlink($tmp);
                        usleep((int) config('demo.photo_rate_limit_us', 200000));

                        return $cachePath;
                    }
                    @unlink($tmp);
                }
                Log::warning('demo.photos: remote download rejected', ['url' => $url, 'attempt' => $attempt]);
            } catch (\Throwable $e) {
                Log::warning('demo.photos: remote download failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /** HSL → RGB helper. */
    protected function hsl(int $h, int $s, int $l): array
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = function ($t) use ($p, $q) {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }

            return $p;
        };

        return [(int) round($f($h + 1 / 3) * 255), (int) round($f($h) * 255), (int) round($f($h - 1 / 3) * 255)];
    }
}
