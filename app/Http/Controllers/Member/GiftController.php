<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use App\Services\GiftService;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function catalog(Request $request, GiftService $gifts)
    {
        return response()->json($gifts->catalog());
    }

    public function send(Request $request, User $user, GiftService $gifts, ChatService $chat)
    {
        $this->authorize('view', $user);
        $request->validate([
            'gift_code' => ['required', 'string', 'exists:gifts,code'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:500'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = null;
        if ($request->input('conversation_id')) {
            $conversation = Conversation::findOrFail($request->input('conversation_id'));
            $this->authorize('send', $conversation);
        } else {
            $conversation = $chat->findOrCreateDirect($request->user(), $user);
        }

        try {
            $txn = $gifts->send($request->user(), $user, $request->string('gift_code'), (int) $request->input('quantity', 1), $conversation, null, $request->input('note'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($txn, 201);
    }
}
