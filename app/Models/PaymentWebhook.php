<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_gateway_id', 'payment_id', 'gateway', 'event_type',
        'payload', 'response_code', 'is_processed', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function gatewayModel(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function markProcessed(int $responseCode = 200): bool
    {
        return $this->update([
            'is_processed' => true,
            'processed_at' => now(),
            'response_code' => $responseCode,
        ]);
    }
}
