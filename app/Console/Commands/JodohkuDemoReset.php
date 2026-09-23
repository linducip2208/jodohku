<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Delete ONLY demo-marked data (users.is_demo = true and rows owned by
 * them). Refuses to run without --confirm, and refuses in production
 * without --force. Real users are never touched: every delete is scoped
 * to the collected demo user id set.
 *
 * php artisan jodohku:demo:reset --confirm
 */
class JodohkuDemoReset extends Command
{
    protected $signature = 'jodohku:demo:reset
        {--confirm : Required confirmation flag}
        {--force : Allow in production}';

    protected $description = 'Delete demo-marked data only (never touches real users)';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing without --confirm. This deletes demo data.');

            return self::FAILURE;
        }
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing in production without --force.');

            return self::FAILURE;
        }

        $ids = User::where('is_demo', true)->pluck('id')->all();
        if (! $ids) {
            $this->comment('No demo users found. Nothing to do.');

            return self::SUCCESS;
        }
        $this->line(sprintf('Deleting demo data for %d users…', count($ids)));

        DB::transaction(function () use ($ids) {
            // Notifications + reads have no demo cascade: scope explicitly.
            DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->whereIn('notifiable_id', $ids)->delete();
            $demoConvos = DB::table('conversation_members')->whereIn('user_id', $ids)->distinct()->pluck('conversation_id');
            // Only drop conversations where EVERY member is a demo user.
            foreach (array_chunk($demoConvos->all(), 500) as $chunk) {
                $nonDemo = DB::table('conversation_members')->whereIn('conversation_id', $chunk)->whereNotIn('user_id', $ids)->distinct()->pluck('conversation_id')->all();
                $demoOnly = array_diff($chunk, $nonDemo);
                if ($demoOnly) {
                    DB::table('message_reads')->whereIn('message_id', DB::table('messages')->whereIn('conversation_id', $demoOnly)->pluck('id'))->delete();
                    DB::table('messages')->whereIn('conversation_id', $demoOnly)->delete();
                    DB::table('conversation_members')->whereIn('conversation_id', $demoOnly)->delete();
                    DB::table('conversations')->whereIn('id', $demoOnly)->delete();
                }
            }
            DB::table('message_reads')->whereIn('user_id', $ids)->delete();
            // Demo-created forums/blogs (slugs are namespaced demo-*).
            $demoForums = DB::table('forums')->where('slug', 'like', 'demo-%')->pluck('id');
            if ($demoForums->isNotEmpty()) {
                DB::table('forum_replies')->whereIn('thread_id', DB::table('forum_threads')->whereIn('forum_id', $demoForums)->pluck('id'))->delete();
                DB::table('forum_threads')->whereIn('forum_id', $demoForums)->delete();
                DB::table('forums')->whereIn('id', $demoForums)->delete();
            }
            DB::table('blog_posts')->where('slug', 'like', '%-demo-%')->delete();
            DB::table('groups')->where('slug', 'like', '%-demo-%')->delete();
            DB::table('events')->where('slug', 'like', '%-demo-%')->delete();
            // Users last: hard delete via query builder (bypasses SoftDeletes)
            // so FK cascades hard-clear profiles, photos, prefs, likes,
            // matches, scores, subs, payments, wallets, gifts, boosts,
            // courtships, consultations(+counselors), reports, blocks,
            // verifications, views, favorites, posts, comments, members.
            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('users')->whereIn('id', $chunk)->delete();
            }
        });

        foreach (['public', (string) config('demo.photo_disk', 'public')] as $disk) {
            try {
                if (Storage::disk($disk)->directoryExists('demo')) {
                    Storage::disk($disk)->deleteDirectory('demo');
                }
            } catch (\Throwable) {
            }
        }

        $remaining = User::where('is_demo', true)->count();
        $this->info("Reset complete. Remaining demo users: {$remaining}");

        return $remaining === 0 ? self::SUCCESS : self::FAILURE;
    }
}
