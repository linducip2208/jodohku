<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'credits', 'bonus_credits', 'price', 'currency', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** @param Builder<CreditProduct> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function totalCredits(): int
    {
        return $this->credits + $this->bonus_credits;
    }
}
