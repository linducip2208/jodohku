<?php

namespace App\Models;

use App\Enums\CreditTxnType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'gift_id', 'sender_id', 'receiver_id', 'conversation_id',
        'message_id', 'quantity', 'credits_spent', 'note',
    ];

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public static function send(User $sender, User $receiver, Gift $gift, int $quantity = 1, array $extra = []): self
    {
        $total = $gift->credit_price * $quantity;

        if ($total > 0) {
            CreditTransaction::record($sender, CreditTxnType::Spend, $total, [
                'description' => "Gift {$gift->name} x{$quantity} to user {$receiver->id}",
            ]);
        }

        return static::create(array_merge($extra, [
            'gift_id' => $gift->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'quantity' => $quantity,
            'credits_spent' => $total,
        ]));
    }
}
