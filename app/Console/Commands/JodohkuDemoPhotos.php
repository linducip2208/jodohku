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

    protected $description = 'Generate/cache demo avatars (generated placeholders by default, local_library supported)';

    public function handle(DemoPhotoProvider $provider): int
    {
        $limit = max(1, (int) $this->option('users'));
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');
        $disk = (string) config('demo.photo_disk', 'public');
        $this->line('Photo driver: '.$provider->driver().' (disk: '.$disk.')');
        if ($provider->driver() === 'local_library') {
            $counts = $provider->libraryCounts();
            $this->line(sprintf('Photo library: male=%d female=%d total=%d', $counts['male'], $counts['female'], $counts['all']));
        }

        $ids = User::where('is_demo', true)->orderBy('id')->limit($limit)->pluck('id');
        // Target per user honors DEMO_PHOTOS_PER_USER (default 1.5): every
        // user gets 1 photo, ~half deterministically get a 2nd (index 1).
        $targetOf = fn (int $id) => 1 + (crc32('photo2:'.$id) % 2 === 0 ? 1 : 0);
        $existing = DB::table('profile_photos')->whereIn('user_id', $ids)
            ->selectRaw('user_id, sort_order, MAX(is_primary) as has_primary')
            ->groupBy('user_id', 'sort_order')->get()->groupBy('user_id');
        $users = User::whereIn('id', $ids)->get(['id', 'display_name', 'gender'])->keyBy('id');
        $todo = [];
        foreach ($ids as $id) {
            $have = isset($existing[$id]) ? $existing[$id]->pluck('sort_order')->all() : [];
            $hasPrimary = isset($existing[$id]) && $existing[$id]->contains(fn ($r) => (bool) $r->has_primary);
            for ($i = 0; $i < $targetOf((int) $id); $i++) {
                if (! $force && in_array($i, $have, true)) {
                    continue;
                }
                $todo[] = ['id' => (int) $id, 'index' => $i, 'primary' => $i === 0 && ! $hasPrimary];
            }
        }
        $this->line(sprintf('Demo users: %d, photos to generate: %d', $ids->count(), count($todo)));

        if ($dry) {
            $this->comment('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $created = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar(max(1, count($todo)));
        $rows = [];
        foreach ($todo as $task) {
            $u = $users[$task['id']] ?? null;
            $gender = $u?->gender;
            $gender = $gender instanceof \BackedEnum ? $gender->value : (string) ($gender ?? '');
            try {
                $path = $provider->avatar($task['id'], (string) ($u?->display_name ?? 'Member'), $task['index'], $disk, $force, $gender ?: null);
            } catch (\Throwable $e) {
                $this->warn("user {$task['id']}: ".$e->getMessage());
                $path = null;
            }
            if ($path) {
                $rows[] = [
                    'user_id' => $task['id'], 'path' => $path, 'thumbnail_path' => null,
                    'sort_order' => $task['index'], 'is_primary' => $task['primary'],
                    'is_approved' => true, 'is_private' => false,
                    'status' => 'approved', 'file_hash' => sha1($path.$task['id'].$task['index']),
                    'width' => 480, 'height' => 600,
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
        $this->info(sprintf('Users processed: %d', $ids->count()));
        $this->info(sprintf('Photos generated: %d', $created));
        $this->info(sprintf('Fallback photos: %d', $provider->fallbackCount()));
        if ($failed > 0) {
            $this->warn("Failed slots: {$failed}");
        }

        return self::SUCCESS;
    }

    protected function insertPhotoRows(array &$rows): void
    {
        if (! $rows) {
            return;
        }
        // Idempotent per (user, slot): skip exact duplicates only, so
        // top-up runs can add index 1 next to an existing index 0.
        $have = DB::table('profile_photos')->whereIn('user_id', array_column($rows, 'user_id'))
            ->selectRaw('user_id, sort_order')->get()
            ->map(fn ($r) => $r->user_id.':'.$r->sort_order)->flip()->all();
        $fresh = array_values(array_filter($rows, fn ($r) => ! isset($have[$r['user_id'].':'.$r['sort_order']])));
        foreach (array_chunk($fresh, 500) as $chunk) {
            DB::table('profile_photos')->insert($chunk);
        }
        $rows = [];
    }
}
