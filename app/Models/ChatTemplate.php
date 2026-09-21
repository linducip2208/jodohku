<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'title', 'body', 'category', 'language', 'variables', 'is_active',
    ];

    protected function casts(): array
    {
        return ['variables' => 'array', 'is_active' => 'boolean'];
    }

    /** @param Builder<ChatTemplate> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function render(array $variables = []): string
    {
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $body = str_replace(['{'.$key.'}', ':'.$key], (string) $value, $body);
        }

        return $body;
    }
}
