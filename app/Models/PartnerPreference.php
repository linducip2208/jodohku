<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Importance;
use App\Enums\MaritalStatus;
use App\Enums\RelationshipGoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'min_age', 'max_age', 'gender_preference', 'max_distance_km',
        'city', 'province', 'religion', 'religion_importance', 'education',
        'education_importance', 'marital_status', 'marital_importance',
        'smoking_preference', 'drinking_preference', 'relationship_goal',
        'relationship_importance', 'min_height_cm', 'max_height_cm',
        'verified_only', 'photo_only',
    ];

    protected function casts(): array
    {
        return [
            'gender_preference' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'relationship_goal' => RelationshipGoal::class,
            'religion_importance' => Importance::class,
            'education_importance' => Importance::class,
            'marital_importance' => Importance::class,
            'relationship_importance' => Importance::class,
            'verified_only' => 'boolean',
            'photo_only' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchesAge(?int $age): bool
    {
        if ($age === null) {
            return true;
        }
        if ($this->min_age !== null && $age < $this->min_age) {
            return false;
        }
        if ($this->max_age !== null && $age > $this->max_age) {
            return false;
        }

        return true;
    }
}
