<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_id', 'user_id', 'topic', 'notes', 'share_report', 'shared_report_id',
        'scheduled_at', 'duration_minutes', 'status', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConsultationStatus::class,
            'scheduled_at' => 'datetime',
            'decided_at' => 'datetime',
            'share_report' => 'boolean',
        ];
    }

    public function sharedReport(): BelongsTo
    {
        return $this->belongsTo(CompatibilityReport::class, 'shared_report_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(Counselor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
