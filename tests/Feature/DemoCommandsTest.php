<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Demo\DemoPhotoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_refuses_without_confirm(): void
    {
        $this->artisan('jodohku:demo:reset')
            ->assertFailed();
    }

    public function test_reset_only_removes_demo_data(): void
    {
        $real = User::factory()->create(['email' => 'real@example.test']);
        User::factory()->create(['email' => 'demo00001@demo.jodohku.test', 'is_demo' => true]);

        $this->artisan('jodohku:demo:reset', ['--confirm' => true])
            ->assertSuccessful();

        $this->assertTrue(User::where('id', $real->id)->exists());
        $this->assertEquals(0, User::where('is_demo', true)->count());
    }

    public function test_demo_dry_run_writes_nothing(): void
    {
        $this->artisan('jodohku:demo', ['--users' => 10, '--dry-run' => true])
            ->assertSuccessful();
        $this->assertEquals(0, User::where('is_demo', true)->count());
    }

    public function test_demo_small_run_builds_connected_dataset(): void
    {
        Storage::fake('public');
        $this->artisan('jodohku:demo', [
            '--users' => 12, '--seed' => 5, '--messages' => 60,
            '--posts' => 10, '--comments' => 30, '--groups' => 2,
            '--events' => 2, '--forums' => 8,
        ])->assertSuccessful();

        $ids = User::where('email', 'like', 'demo%@demo.jodohku.test')->orderBy('id')->pluck('id');
        $this->assertCount(12, $ids);
        $this->assertEquals(12, DB::table('profiles')->whereIn('user_id', $ids)->count());
        $this->assertGreaterThan(0, DB::table('profile_photos')->whereIn('user_id', $ids)->count());
        $this->assertGreaterThan(0, DB::table('likes')->count());
        $this->assertGreaterThan(0, DB::table('messages')->count());
        $this->assertGreaterThan(0, DB::table('posts')->count());
        // Every message belongs to a conversation with two members.
        $this->assertEquals(0, DB::table('messages')->whereNotIn('conversation_id', DB::table('conversations')->pluck('id'))->count());
        // Demo login password works.
        $this->assertTrue(Hash::check('password', User::where('email', 'like', 'demo%@demo.jodohku.test')->first()->password));
    }

    public function test_demo_relationships_are_deterministic_per_seed(): void
    {
        Storage::fake('public');
        $args = ['--users' => 12, '--seed' => 99, '--messages' => 60, '--posts' => 10, '--comments' => 20, '--groups' => 2, '--events' => 2, '--forums' => 8];
        $this->artisan('jodohku:demo', $args)->assertSuccessful();
        $likesA = DB::table('likes')->orderBy('liker_id')->orderBy('liked_id')->get(['liker_id', 'liked_id'])->map(fn ($r) => $r->liker_id.':'.$r->liked_id)->all();

        $this->artisan('jodohku:demo:reset', ['--confirm' => true])->assertSuccessful();
        $this->artisan('jodohku:demo', $args)->assertSuccessful();
        $likesB = DB::table('likes')->orderBy('liker_id')->orderBy('liked_id')->get(['liker_id', 'liked_id'])->map(fn ($r) => $r->liker_id.':'.$r->liked_id)->all();

        // Same relative graph (normalize ids: sqlite AUTOINCREMENT does not
        // restart after reset, so compare id offsets instead of absolutes).
        $rel = function ($pairs) {
            $split = array_map(fn ($p) => array_map('intval', explode(':', $p)), $pairs);
            $all = array_merge(...array_map(fn ($p) => [$p[0], $p[1]], $split));
            $min = min($all);
            $n = array_map(fn ($p) => [$p[0] - $min, $p[1] - $min], $split);
            sort($n);

            return $n;
        };
        $this->assertEquals(count($likesA), count($likesB));
        $this->assertEquals($rel($likesA), $rel($likesB));
    }

    public function test_photo_provider_generated_is_idempotent_and_safe(): void
    {
        Storage::fake('public');
        $provider = app(DemoPhotoProvider::class);
        $a = $provider->avatar(4242, 'Siti', 0, 'public');
        $b = $provider->avatar(4242, 'Siti', 0, 'public');
        $this->assertNotNull($a);
        $this->assertEquals($a, $b);
        $this->assertStringStartsWith('demo/avatars/', $a);
        $this->assertTrue(Storage::disk('public')->exists($a));
    }
}
