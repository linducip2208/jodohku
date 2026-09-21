<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VerificationRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'status', 'notes', 'reviewed_by', 'reviewed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VerificationType::class,
            'status' => VerificationStatus::class,
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    /** @param Builder<VerificationRequest> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VerificationStatus::Pending);
    }

    public function approve(User $reviewer): bool
    {
        $result = $this->update([
            'status' => VerificationStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        if ($result) {
            $this->user()->update(['is_verified' => true]);
        }

        return $result;
    }

    public function reject(User $reviewer, string $notes = ''): bool
    {
        return $this->update([
            'status' => VerificationStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'notes' => $notes ?: $this->notes,
        ]);
    }
}
