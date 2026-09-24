<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Hashtag extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'posts_count'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_hashtag')->withTimestamps();
    }

    public static function normalize(string $tag): string
    {
        return Str::slug(mb_strtolower(trim($tag, "# \t\n\r\0\x0B")));
    }
}
