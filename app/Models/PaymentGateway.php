<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(PaymentGatewaySetting::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @param Builder<PaymentGateway> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function setting(string $key, string $environment = 'production'): ?string
    {
        $value = $this->settings->first(fn ($s) => $s->key === $key && $s->environment === $environment)
            ?? $this->settings()->where('key', $key)->where('environment', $environment)->first();

        return $value?->value;
    }
}
