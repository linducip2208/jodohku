<?php

namespace App\Models;

use App\Enums\PrivacyVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'cover_path', 'visibility',
        'category', 'interests', 'rules', 'members_count', 'posts_count', 'event_id',
    ];

    protected function casts(): array
    {
        return ['visibility' => PrivacyVisibility::class, 'interests' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            $group->slug ??= Str::slug($group->name).'-'.Str::lower(Str::random(5));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function isManager(int $userId): bool
    {
        return $this->owner_id === $userId
            || $this->members()->where('user_id', $userId)->whereIn('role', ['admin', 'moderator'])->exists();
    }

    /**
     * @param  Builder<Group>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where(function ($q) use ($viewer) {
            $q->where('visibility', PrivacyVisibility::Public);
            if ($viewer) {
                $q->orWhere('visibility', PrivacyVisibility::MembersOnly);
                $q->orWhereIn('id', GroupMember::where('user_id', $viewer->id)->select('group_id'));
                if ($viewer->isStaff()) {
                    $q->orWhereIn('visibility', [PrivacyVisibility::Private, PrivacyVisibility::Hidden]);
                }
            }
        });
    }
}
