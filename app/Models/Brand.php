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
        'features', 'footer',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'features' => 'array',
            'footer' => 'array',
        ];
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
