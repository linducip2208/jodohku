<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GiftService
{
    public function __construct(protected CreditService $credits, protected AuditService $audit) {}

    public function catalog(): Collection
    {
        return Gift::active()->get();
    }

    public function received(User $user, int $perPage = 25)
    {
        return GiftTransaction::with(['gift', 'sender'])->where('receiver_id', $user->id)
            ->latest('id')->paginate($perPage);
    }

    public function sent(User $user, int $perPage = 25)
    {
        return GiftTransaction::with(['gift', 'receiver'])->where('sender_id', $user->id)
            ->latest('id')->paginate($perPage);
    }

    public function stats(User $user): array
    {
        return [
            'gifts_received' => GiftTransaction::where('receiver_id', $user->id)->count(),
            'gifts_sent' => GiftTransaction::where('sender_id', $user->id)->count(),
            'credits_spent' => (float) GiftTransaction::where('sender_id', $user->id)->sum('credits_spent'),
            'top_sender' => GiftTransaction::with('sender')->where('receiver_id', $user->id)
                ->selectRaw('sender_id, COUNT(*) as total')->groupBy('sender_id')->orderByDesc('total')->first()?->sender?->displayName(),
            'popular_gifts' => GiftTransaction::with('gift')
                ->selectRaw('gift_id, COUNT(*) as total')->groupBy('gift_id')->orderByDesc('total')->limit(5)
                ->get()->map(fn ($t) => ['gift' => $t->gift?->name, 'total' => $t->total])->all(),
        ];
    }

    public function send(User $sender, User $receiver, string $giftCode, int $quantity = 1, ?Conversation $conversation = null, ?Message $message = null, ?string $note = null): GiftTransaction
    {
        if (! config('jodohku.features.gifts', true)) {
            throw new \RuntimeException('Gifts feature disabled.');
        }
        if ((int) $sender->id === (int) $receiver->id) {
            throw new \InvalidArgumentException('Cannot send a gift to yourself.');
        }
        if (Block::existsBetween((int) $sender->id, (int) $receiver->id)) {
            throw new \RuntimeException('Cannot send a gift to this user.');
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
