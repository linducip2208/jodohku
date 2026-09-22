<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuestionCategory extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'slug', 'name', 'description', 'sort_order', 'is_active'];

    protected static function booted(): void
    {
        static::creating(function (QuestionCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->key ?: $category->name);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
