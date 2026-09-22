<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'host_id', 'title', 'slug', 'description', 'cover_path', 'city',
        'latitude', 'longitude',
        'venue', 'starts_at', 'ends_at', 'capacity', 'price', 'status',
        'is_online', 'online_url',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => EventStatus::class,
            'is_online' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->slug ??= Str::slug($event->title).'-'.Str::lower(Str::random(5));
            $event->status ??= EventStatus::Draft;
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    /** @param Builder<Event> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [EventStatus::Published->value, EventStatus::Ongoing->value]);
    }

    public function seatsLeft(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->members()->count());
    }
}
