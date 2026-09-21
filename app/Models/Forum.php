<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Forum extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function visibleThreads(): HasMany
    {
        return $this->threads()->where('is_hidden', false);
    }
}
