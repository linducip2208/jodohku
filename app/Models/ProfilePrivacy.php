<?php

namespace App\Models;

use App\Enums\PrivacyVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePrivacy extends Model
{
    use HasFactory;

    protected $table = 'profile_privacy';

    protected $fillable = [
        'user_id', 'photos_visibility', 'videos_visibility', 'bio_visibility',
        'location_visibility', 'online_visibility', 'age_visibility',
        'followers_visibility', 'following_visibility', 'posts_visibility',
        'stories_visibility',
        'show_distance', 'show_online_status', 'allow_profile_views',
        'is_incognito', 'is_public_index',
    ];

    protected function casts(): array
    {
        return [
            'photos_visibility' => PrivacyVisibility::class,
            'videos_visibility' => PrivacyVisibility::class,
            'bio_visibility' => PrivacyVisibility::class,
            'location_visibility' => PrivacyVisibility::class,
            'online_visibility' => PrivacyVisibility::class,
            'age_visibility' => PrivacyVisibility::class,
            'followers_visibility' => PrivacyVisibility::class,
            'following_visibility' => PrivacyVisibility::class,
            'posts_visibility' => PrivacyVisibility::class,
            'stories_visibility' => PrivacyVisibility::class,
            'show_distance' => 'boolean',
            'show_online_status' => 'boolean',
            'allow_profile_views' => 'boolean',
            'is_incognito' => 'boolean',
            'is_public_index' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canSee(User $viewer, PrivacyVisibility $visibility, bool $isMatch = false): bool
    {
        return match ($visibility) {
            PrivacyVisibility::Public => true,
            PrivacyVisibility::MembersOnly => $viewer->exists,
            PrivacyVisibility::PremiumOnly => $viewer->isPremium(),
            PrivacyVisibility::MatchesOnly => $isMatch,
            PrivacyVisibility::Private, PrivacyVisibility::Hidden => false,
        };
    }
}
