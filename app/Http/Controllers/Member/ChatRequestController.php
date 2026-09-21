<?php

namespace App\Http\Controllers\Member;

use App\Events\ChatRequestCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequestActionRequest;
use App\Models\ChatRequest;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $requests = ChatRequest::where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
            ->with(['sender', 'receiver'])->latest('id')->paginate(20);

        return $request->wantsJson()
            ? response()->json($requests)
            : view('member.chat.requests', ['requests' => $requests]);
    }

    public function store(Request $request, User $user, ChatService $chat)
    {
        $this->authorize('view', $user);
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        $me = $request->user();

        $existing = ChatRequest::where('sender_id', $me->id)->where('receiver_id', $user->id)
            ->where('status', 'pending')->first();
        if ($existing) {
            return response()->json($existing);
        }

        $chatRequest = DB::transaction(function () use ($me, $user, $request) {
            $r = ChatRequest::create([
                'sender_id' => $me->id,
                'receiver_id' => $user->id,
                'status' => 'pending',
                'message' => $request->input('message'),
                'expires_at' => now()->addHours((int) config('chat.chat_request.expiry_hours', 72)),
            ]);
            event(new ChatRequestCreated($r->fresh()));

            return $r;
        });

        return response()->json($chatRequest, 201);
    }

    public function act(ChatRequestActionRequest $request, ChatRequest $chatRequest, ChatService $chat)
    {
        $user = $request->user();
        if ((int) $chatRequest->receiver_id !== (int) $user->id && (int) $chatRequest->sender_id !== (int) $user->id) {
            abort(403);
        }
        $action = $request->string('action')->toString();

        if ($action === 'cancel') {
            if ((int) $chatRequest->sender_id !== (int) $user->id) {
                abort(403);
            }
            $chatRequest->update(['status' => 'cancelled', 'responded_at' => now()]);

            return response()->json(['message' => 'Cancelled.']);
        }

        if ((int) $chatRequest->receiver_id !== (int) $user->id) {
            abort(403);
        }

        if ($action === 'accept') {
            $chatRequest->accept();
            $conversation = $chat->findOrCreateDirect($chatRequest->sender, $user);

            return response()->json(['message' => 'Accepted.', 'conversation_id' => $conversation->id]);
        }

        $chatRequest->decline();

        return response()->json(['message' => 'Declined.']);
    }
}
