<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewaySetting extends Model
{
    use HasFactory;

    protected $fillable = ['payment_gateway_id', 'environment', 'key', 'value', 'is_secret'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * Secrets are encrypted at rest with Crypt. Plaintext legacy rows
     * still decrypt transparently (try decrypt, fallback to raw).
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $stored) {
                if ($stored === null || $stored === '') {
                    return $stored;
                }
                try {
                    return Crypt::decryptString($stored);
                } catch (\Throwable) {
                    return $stored;
                }
            },
            set: function (?string $incoming) {
                if ($incoming === null || $incoming === '') {
                    return $incoming;
                }
                // Encrypt only when the row is marked secret; is_secret may be
                // set in the same create() payload, so inspect attributes.
                $isSecret = $this->attributes['is_secret'] ?? false;
                if ($isSecret) {
                    try {
                        // Avoid double-encrypting an already-encrypted payload.
                        Crypt::decryptString($incoming);

                        return $incoming;
                    } catch (\Throwable) {
                        return Crypt::encryptString($incoming);
                    }
                }

                return $incoming;
            },
        );
    }

    public function setSecretValue(string $plain): void
    {
        $this->value = Crypt::encryptString($plain);
    }
}
