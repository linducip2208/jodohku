<?php

namespace App\Http\Controllers\Member;

use App\Enums\ChatRequestStatus;
use App\Events\ChatRequestCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequestActionRequest;
use App\Models\Block;
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
            : view('member.chat.inbox', ['requests' => $requests]);
    }

    public function store(Request $request, User $user, ChatService $chat)
    {
        $this->authorize('view', $user);
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        $me = $request->user();

        $limit = (int) config('chat.rate_limits.requests_per_day', 30);
        $sentToday = ChatRequest::where('sender_id', $me->id)->whereDate('created_at', today())->count();
        if ($sentToday >= $limit) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Batas permintaan chat harian tercapai.'], 429)
                : back()->withErrors(['chat_request' => 'Batas permintaan chat harian tercapai.']);
        }

        if (Block::existsBetween((int) $me->id, (int) $user->id)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Tidak dapat mengirim permintaan chat.'], 403)
                : back()->withErrors(['chat_request' => 'Tidak dapat mengirim permintaan chat.']);
        }

        $existing = ChatRequest::where(fn ($q) => $q
            ->where(fn ($qq) => $qq->where('sender_id', $me->id)->where('receiver_id', $user->id))
            ->orWhere(fn ($qq) => $qq->where('sender_id', $user->id)->where('receiver_id', $me->id)))
            ->where('status', 'pending')->first();
        if ($existing) {
            return $request->wantsJson()
                ? response()->json($existing)
                : back()->with('status', 'Permintaan chat sudah terkirim.');
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

        return $request->wantsJson()
            ? response()->json($chatRequest, 201)
            : back()->with('status', 'Permintaan chat terkirim ✅');
    }

    public function act(ChatRequestActionRequest $request, ChatRequest $chatRequest, ChatService $chat)
    {
        $user = $request->user();
        if ((int) $chatRequest->receiver_id !== (int) $user->id && (int) $chatRequest->sender_id !== (int) $user->id) {
            abort(403);
        }
        $action = $request->string('action')->toString();

        // Terminal states can never be transitioned again; expired counts as closed.
        if ($chatRequest->isExpired() && $chatRequest->status === ChatRequestStatus::Pending) {
            $chatRequest->update(['status' => ChatRequestStatus::Expired, 'responded_at' => now()]);
        }
        if ($chatRequest->status->isFinal()) {
            return response()->json(['message' => 'Permintaan sudah tidak berlaku.'], 422);
        }

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
