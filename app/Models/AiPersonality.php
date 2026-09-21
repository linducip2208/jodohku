<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPersonality extends Model
{
    use HasFactory;

    protected $table = 'ai_personalities';

    protected $fillable = [
        'code', 'name', 'description', 'system_prompt', 'tone', 'language', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function virtualProfiles(): HasMany
    {
        return $this->hasMany(VirtualProfile::class);
    }

    /** @param Builder<AiPersonality> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
