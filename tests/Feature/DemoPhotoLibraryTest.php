<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Demo\DemoPhotoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoPhotoLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function solidJpeg(array $rgb): string
    {
        $img = imagecreatetruecolor(100, 140);
        imagefill($img, 0, 0, imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]));
        ob_start();
        imagejpeg($img, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    protected function seedLibrary(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('demo-faces/male/m1.jpg', $this->solidJpeg([30, 60, 200]));
        Storage::disk('local')->put('demo-faces/male/m2.jpg', $this->solidJpeg([200, 60, 30]));
        Storage::disk('local')->put('demo-faces/female/f1.jpg', $this->solidJpeg([30, 200, 60]));
        config()->set('demo.photo_driver', 'local_library');
        config()->set('demo.photo_library', 'demo-faces');
        config()->set('demo.photo_library_disk', 'local');
    }

    public function test_library_counts_scan_gender_pools(): void
    {
        $this->seedLibrary();
        $counts = app(DemoPhotoProvider::class)->libraryCounts();
        $this->assertEquals(2, $counts['male']);
        $this->assertEquals(1, $counts['female']);
        $this->assertEquals(3, $counts['all']);
    }

    public function test_library_pick_is_deterministic_and_resized(): void
    {
        $this->seedLibrary();
        $provider = app(DemoPhotoProvider::class);

        $a = $provider->avatar(101, 'Budi', 0, 'public', false, 'male');
        $b = $provider->avatar(101, 'Budi', 0, 'public', false, 'male');
        $this->assertNotNull($a);
        $this->assertEquals($a, $b);
        $this->assertStringStartsWith('demo/avatars/', $a);
        $this->assertTrue(Storage::disk('public')->exists($a));
        $info = getimagesize(Storage::disk('public')->path($a));
        $this->assertEquals(480, $info[0]);
        $this->assertEquals(600, $info[1]);
        $this->assertEquals(0, $provider->fallbackCount());
    }

    public function test_library_gender_pools_do_not_cross(): void
    {
        $this->seedLibrary();
        $provider = app(DemoPhotoProvider::class);

        $malePath = $provider->avatar(102, 'Agus', 0, 'public', false, 'male');
        $femalePath = $provider->avatar(103, 'Siti', 0, 'public', false, 'female');
        $this->assertNotNull($malePath);
        $this->assertNotNull($femalePath);
        // Distinct solid-color sources must produce distinct outputs.
        $this->assertNotEquals(
            md5((string) Storage::disk('public')->get($malePath)),
            md5((string) Storage::disk('public')->get($femalePath))
        );
    }

    public function test_empty_library_falls_back_to_generated(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config()->set('demo.photo_driver', 'local_library');
        config()->set('demo.photo_library', 'demo-faces');
        config()->set('demo.photo_library_disk', 'local');

        $provider = app(DemoPhotoProvider::class);
        $this->assertEquals(['male' => 0, 'female' => 0, 'all' => 0], $provider->libraryCounts());

        $path = $provider->avatar(104, 'Rina', 0, 'public', false, 'female');
        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertEquals(1, $provider->fallbackCount());
    }

    public function test_photos_command_honors_per_user_distribution(): void
    {
        Storage::fake('public');
        config()->set('demo.photo_driver', 'generated');
        $users = User::factory()->count(10)->create(['is_demo' => true]);
        $expected = $users->sum(fn ($u) => 1 + (crc32('photo2:'.$u->id) % 2 === 0 ? 1 : 0));

        $this->artisan('jodohku:demo:photos', ['--users' => 10])->assertSuccessful();

        $rows = \DB::table('profile_photos')->whereIn('user_id', $users->pluck('id'))->get();
        $this->assertEquals($expected, $rows->count());
        // Exactly one primary per user, sort orders start at 0, all approved.
        foreach ($users->pluck('id') as $id) {
            $mine = $rows->where('user_id', $id);
            $this->assertEquals(1, $mine->where('is_primary', true)->count());
            $this->assertEquals(range(0, $mine->count() - 1), $mine->pluck('sort_order')->sort()->values()->all());
            $this->assertTrue($mine->every(fn ($r) => $r->status === 'approved' && (bool) $r->is_approved && ! (bool) $r->is_private));
        }

        // Rerun is a pure top-up: row count unchanged.
        $this->artisan('jodohku:demo:photos', ['--users' => 10])->assertSuccessful();
        $this->assertEquals($expected, \DB::table('profile_photos')->whereIn('user_id', $users->pluck('id'))->count());
    }
}
