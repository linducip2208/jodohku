<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompatibilityReport extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'candidate_id', 'score', 'breakdown', 'summary'];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'breakdown' => 'array',
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
