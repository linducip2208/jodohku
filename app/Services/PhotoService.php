<?php

namespace App\Services;

use App\Models\ProfilePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class PhotoService
{
    protected ImageManager $images;

    public function __construct(protected AuditService $audit)
    {
        $this->images = new ImageManager(new Driver);
    }

    /**
     * Full upload pipeline: validate → hash/duplicate → resize → thumbnail →
     * store → pending row. Returns the created ProfilePhoto.
     *
     * @throws \InvalidArgumentException on any validation failure.
     */
    public function upload(User $user, UploadedFile $file, bool $isPrivate = false): ProfilePhoto
    {
        $this->validateFile($file);

        $hash = hash_file('sha256', $file->getRealPath());
        if ($user->photos()->where('file_hash', $hash)->exists()) {
            throw new \InvalidArgumentException('Duplicate photo: this image was already uploaded.');
        }

        $image = $this->images->decode($file->getRealPath());
        $width = $image->width();
        $height = $image->height();
        if ($width < 200 || $height < 200) {
            throw new \InvalidArgumentException('Photo is too small (minimum 200x200).');
        }

        return DB::transaction(function () use ($user, $file, $hash, $image, $width, $height, $isPrivate) {
            $base = 'profile-photos/'.$user->id.'/'.uniqid('p', true);

            $main = clone $image;
            if (method_exists($main, 'scaleDown')) {
                $main->scaleDown(1600, 1600);
            } else {
                $main->resize(1600, 1600, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
            }
            $mainPath = $base.'.jpg';
            Storage::disk('public')->put($mainPath, (string) $main->encode(new JpegEncoder(82)));

            $thumb = clone $image;
            if (method_exists($thumb, 'cover')) {
                $thumb->cover(400, 400);
            } else {
                $thumb->fit(400, 400);
            }
            $thumbPath = $base.'_thumb.jpg';
            Storage::disk('public')->put($thumbPath, (string) $thumb->encode(new JpegEncoder(78)));

            $count = $user->photos()->count();
            $photo = $user->photos()->create([
                'path' => $mainPath,
                'thumbnail_path' => $thumbPath,
                'sort_order' => $count,
                'is_primary' => $count === 0,
                'is_private' => $isPrivate,
                'is_approved' => false,
                'status' => 'pending',
                'file_hash' => $hash,
                'width' => $width,
                'height' => $height,
            ]);

            $this->audit->log('photo.uploaded', $user, $photo, [], ['bytes' => $file->getSize()]);

            return $photo;
        });
    }

    public function approve(ProfilePhoto $photo, User $reviewer): ProfilePhoto
    {
        $photo->update([
            'status' => 'approved', 'is_approved' => true,
            'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
        ]);
        $this->audit->log('photo.approved', $reviewer, $photo);

        return $photo->fresh();
    }

    public function reject(ProfilePhoto $photo, User $reviewer, string $reason = ''): ProfilePhoto
    {
        $photo->update([
            'status' => 'rejected', 'is_approved' => false,
            'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
        ]);
        $this->audit->log('photo.rejected', $reviewer, $photo, [], ['reason' => $reason]);

        return $photo->fresh();
    }

    public function destroy(User $owner, ProfilePhoto $photo): void
    {
        if ((int) $photo->user_id !== (int) $owner->id) {
            abort(403);
        }
        DB::transaction(function () use ($photo) {
            Storage::disk('public')->delete([$photo->path, $photo->thumbnail_path]);
            $photo->delete();
        });
        $this->audit->log('photo.deleted', $owner, $photo);
    }

    /** Photos of $owner visible to $viewer (self/staff see all incl. pending). */
    public function visibleTo(User $owner, ?User $viewer)
    {
        $query = $owner->photos()->ordered();
        $isSelf = $viewer && (int) $viewer->id === (int) $owner->id;
        $isStaff = $viewer && $viewer->isStaff();
        if (! ($isSelf || $isStaff)) {
            $query->where('status', 'approved');
        }

        return $query->get();
    }

    protected function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Upload failed.');
        }
        if ($file->getSize() > 8 * 1024 * 1024) {
            throw new \InvalidArgumentException('Photo exceeds 8MB.');
        }
        // Never trust the extension: verify real MIME via finfo.
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Only JPG, PNG or WebP photos are allowed.');
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowedByMime = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        if (! in_array($ext, $allowedByMime[$mime] ?? [], true)) {
            throw new \InvalidArgumentException('File extension does not match image content.');
        }
        if (@getimagesize($file->getRealPath()) === false) {
            throw new \InvalidArgumentException('File is not a readable image.');
        }
    }
}
