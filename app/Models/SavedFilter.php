<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedFilter extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'filters', 'is_default'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Filter keys allowed to persist (subset of DiscoveryService filters). */
    public const ALLOWED = [
        'gender', 'city', 'education', 'occupation', 'religion',
        'relationship_goal', 'verified', 'online', 'premium',
        'min_age', 'max_age', 'max_distance_km', 'sort',
    ];
}
