<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generate PWA icons (no designer assets needed): rose gradient rounded
 * square + white ring + heart-ish dot, rendered locally with GD.
 *
 * php artisan pwa:icons
 */
class PwaIcons extends Command
{
    protected $signature = 'pwa:icons';

    protected $description = 'Generate PWA icons into public/icons';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('GD missing.');

            return self::FAILURE;
        }
        $dir = public_path('icons');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        foreach ([192, 512] as $size) {
            $img = imagecreatetruecolor($size, $size);
            $r1 = [244, 63, 94];
            $r2 = [139, 92, 246];
            for ($y = 0; $y < $size; $y++) {
                $t = $y / max(1, $size - 1);
                imagefilledrectangle($img, 0, $y, $size, $y, imagecolorallocate($img,
                    (int) ($r1[0] + ($r2[0] - $r1[0]) * $t),
                    (int) ($r1[1] + ($r2[1] - $r1[1]) * $t),
                    (int) ($r1[2] + ($r2[2] - $r1[2]) * $t)));
            }
            $cx = (int) ($size / 2);
            $d = (int) ($size * 0.52);
            $white = imagecolorallocate($img, 255, 255, 255);
            imagefilledellipse($img, $cx, $cx, $d, $d, $white);
            $inner = imagecolorallocate($img, 244, 63, 94);
            imagefilledellipse($img, $cx, $cx, (int) ($d * 0.62), (int) ($d * 0.62), $inner);
            $path = $dir.'/icon-'.$size.'.png';
            imagepng($img, $path);
            imagedestroy($img);
            $this->line("wrote icons/icon-{$size}.png");
        }
        // Maskable + Apple touch reuse the 512/192 renders (safe-area padding baked by shape).
        copy($dir.'/icon-512.png', $dir.'/icon-maskable.png');
        copy($dir.'/icon-192.png', $dir.'/apple-touch-icon.png');
        $this->info('PWA icons ready.');

        return self::SUCCESS;
    }
}
