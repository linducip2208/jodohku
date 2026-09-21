<?php

namespace App\Models;

use App\Enums\MaritalStatus;
use App\Enums\RelationshipGoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'headline', 'bio', 'occupation', 'education', 'religion',
        'ethnicity', 'height_cm', 'weight_kg', 'body_type', 'smoking', 'drinking',
        'marital_status', 'children_count', 'want_children', 'relationship_goal',
        'languages', 'zodiac', 'is_complete', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'marital_status' => MaritalStatus::class,
            'relationship_goal' => RelationshipGoal::class,
            'is_complete' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completenessScore(): int
    {
        $fields = ['headline', 'bio', 'occupation', 'education', 'religion', 'height_cm', 'relationship_goal'];
        $filled = collect($fields)->filter(fn ($f) => ! empty($this->{$f}))->count();

        return (int) round($filled / count($fields) * 100);
    }
}
