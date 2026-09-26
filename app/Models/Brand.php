<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'tagline', 'primary_color', 'secondary_color',
        'logo_path', 'favicon_path', 'domain', 'is_active', 'is_default',
        'expires_at', 'max_users', 'features', 'footer', 'content',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'expires_at' => 'datetime',
            'features' => 'array',
            'footer' => 'array',
            'content' => 'array',
        ];
    }

    /** Commercial license check: expired brands are treated as inactive. */
    public function licensed(): bool
    {
        return (bool) $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }
        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://') || str_starts_with($this->logo_path, '/')) {
            return $this->logo_path;
        }

        return asset('storage/'.ltrim($this->logo_path, '/'));
    }

    public function faviconUrl(): ?string
    {
        if (! $this->favicon_path) {
            return null;
        }
        if (str_starts_with($this->favicon_path, 'http://') || str_starts_with($this->favicon_path, 'https://') || str_starts_with($this->favicon_path, '/')) {
            return $this->favicon_path;
        }

        return asset('storage/'.ltrim($this->favicon_path, '/'));
    }

    /** Per-brand feature flag with fallback to global config default. */
    public function featureOn(string $key, bool $default = true): bool
    {
        $features = $this->features ?? [];

        return (bool) ($features[$key] ?? $default);
    }
}
