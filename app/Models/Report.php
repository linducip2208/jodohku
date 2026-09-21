<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reporter_id', 'reported_user_id', 'reportable_type', 'reportable_id',
        'reason', 'details', 'status', 'handled_by', 'resolution_notes', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reportable_type', 'reportable_id');
    }

    /** @param Builder<Report> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ReportStatus::Pending->value, ReportStatus::Reviewing->value]);
    }

    public function resolve(User $handler, string $notes = ''): bool
    {
        return $this->update([
            'status' => ReportStatus::Resolved,
            'handled_by' => $handler->id,
            'resolution_notes' => $notes,
            'resolved_at' => now(),
        ]);
    }
}
