<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Demo\DemoPhotoProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * (Re)generate demo avatars via the configured DemoPhotoProvider.
 *
 * php artisan jodohku:demo:photos --users=5000 [--force] [--dry-run]
 */
class JodohkuDemoPhotos extends Command
{
    protected $signature = 'jodohku:demo:photos
        {--users=5000 : Max demo users to cover}
        {--force : Regenerate even when a photo already exists}
        {--dry-run : Report what would happen}';

    protected $description = 'Generate/cache demo avatars (safe generated placeholders by default)';

    public function handle(DemoPhotoProvider $provider): int
    {
        $limit = max(1, (int) $this->option('users'));
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');
        $this->line('Photo driver: '.$provider->driver().' (disk: '.config('demo.photo_disk', 'public').')');

        $ids = User::where('is_demo', true)->orderBy('id')->limit($limit)->pluck('id');
        $have = DB::table('profile_photos')->whereIn('user_id', $ids)->groupBy('user_id')->selectRaw('user_id, count(*) as c')->pluck('c', 'user_id');
        $missing = $ids->filter(fn ($id) => $force || ! isset($have[$id]))->values();
        $this->line(sprintf('Demo users: %d, with photos: %d, to process: %d', $ids->count(), count($have), $missing->count()));

        if ($dry) {
            $this->comment('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $names = User::whereIn('id', $missing)->pluck('display_name', 'id');
        $created = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($missing->count());
        $rows = [];
        foreach ($missing as $id) {
            try {
                $path = $provider->avatar($id, (string) ($names[$id] ?? 'Member'), 0, (string) config('demo.photo_disk', 'public'), $force);
            } catch (\Throwable $e) {
                $this->warn("user {$id}: ".$e->getMessage());
                $path = null;
            }
            if ($path) {
                $rows[] = [
                    'user_id' => $id, 'path' => $path, 'thumbnail_path' => null,
                    'sort_order' => 0, 'is_primary' => true, 'is_approved' => true, 'is_private' => false,
                    'status' => 'approved', 'file_hash' => sha1($path.$id), 'width' => 480, 'height' => 600,
                    'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
                ];
                $created++;
            } else {
                $failed++;
            }
            $bar->advance();
            if (count($rows) >= 500) {
                $this->insertPhotoRows($rows);
                $rows = [];
            }
        }
        $bar->finish();
        $this->line('');
        $this->insertPhotoRows($rows);
        $this->info("Done. created={$created} failed={$failed}");

        return self::SUCCESS;
    }

    protected function insertPhotoRows(array &$rows): void
    {
        if (! $rows) {
            return;
        }
        // Skip users that already have a primary photo (idempotent).
        $have = DB::table('profile_photos')->whereIn('user_id', array_column($rows, 'user_id'))->pluck('user_id')->all();
        $have = array_flip($have);
        $fresh = array_values(array_filter($rows, fn ($r) => ! isset($have[$r['user_id']])));
        foreach (array_chunk($fresh, 500) as $chunk) {
            DB::table('profile_photos')->insert($chunk);
        }
        $rows = [];
    }
}
