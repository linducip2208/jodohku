<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'candidate_id', 'questionnaire_score', 'interest_score',
        'preference_score', 'activity_score', 'total_score', 'breakdown', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'questionnaire_score' => 'decimal:2',
            'interest_score' => 'decimal:2',
            'preference_score' => 'decimal:2',
            'activity_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
