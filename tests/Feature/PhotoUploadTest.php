<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function makeImage(int $w = 800, int $h = 800): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    public function test_valid_upload_creates_pending_photo_with_thumbnail(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        /** @var PhotoService $svc */
        $svc = app(PhotoService::class);
        $photo = $svc->upload($user, $this->makeImage());

        $this->assertEquals('pending', $photo->status);
        $this->assertFalse((bool) $photo->is_approved);
        $this->assertNotNull($photo->file_hash);
        $this->assertEquals(800, (int) $photo->width);
        Storage::disk('public')->assertExists($photo->path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);
    }

    public function test_duplicate_upload_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $svc = app(PhotoService::class);

        // Same bytes twice: second must fail even though temp path differs.
        $bytes = null;
        $make = function () use (&$bytes) {
            $img = imagecreatetruecolor(600, 600);
            $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
            imagejpeg($img, $path, 90);
            imagedestroy($img);
            if ($bytes === null) {
                $bytes = file_get_contents($path);
            } else {
                file_put_contents($path, $bytes);
            }

            return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
        };

        $svc->upload($user, $make());
        $this->expectException(\InvalidArgumentException::class);
        $svc->upload($user, $make());
    }

    public function test_exe_spoof_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'evil').'.jpg';
        file_put_contents($path, "<?php echo 'pwned';");
        $file = new UploadedFile($path, 'shell.jpg', 'image/jpeg', null, true);

        $this->expectException(\InvalidArgumentException::class);
        app(PhotoService::class)->upload($user, $file);
    }

    public function test_others_only_see_approved_photos(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $svc = app(PhotoService::class);
        $photo = $svc->upload($owner, $this->makeImage());

        $this->assertCount(0, $svc->visibleTo($owner, $viewer));
        $this->assertCount(1, $svc->visibleTo($owner, $owner));

        $svc->approve($photo, $admin = User::factory()->create(['role' => \App\Enums\UserRole::Admin]));
        $this->assertCount(1, $svc->visibleTo($owner, $viewer));
    }
}
