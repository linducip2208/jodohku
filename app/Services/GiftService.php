<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GiftService
{
    public function __construct(protected CreditService $credits, protected AuditService $audit) {}

    public function catalog(): \Illuminate\Support\Collection
    {
        return Gift::active()->get();
    }

    public function send(User $sender, User $receiver, string $giftCode, int $quantity = 1, ?Conversation $conversation = null, ?Message $message = null, ?string $note = null): GiftTransaction
    {
        if (! config('jodohku.features.gifts', true)) {
            throw new \RuntimeException('Gifts feature disabled.');
        }

        return DB::transaction(function () use ($sender, $receiver, $giftCode, $quantity, $conversation, $message, $note) {
            $gift = Gift::where('code', $giftCode)->where('is_active', true)->firstOrFail();
            $quantity = max(1, min(99, $quantity));
            $total = $gift->credit_price * $quantity;

            if ($total > 0) {
                $this->credits->spend($sender, $total, "Gift {$gift->name} x{$quantity} to user {$receiver->id}");
            }

            $txn = GiftTransaction::create([
                'gift_id' => $gift->id,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'conversation_id' => $conversation?->id,
                'message_id' => $message?->id,
                'quantity' => $quantity,
                'credits_spent' => $total,
                'note' => $note,
            ]);
            $this->audit->log('gift.sent', $sender, $txn, [], ['gift' => $gift->code, 'qty' => $quantity]);

            return $txn;
        });
    }
}
