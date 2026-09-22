<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\ChatRequest;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /** Peer user ids tied to pending chat requests (either direction). */
    protected function requestPeerIds(User $user)
    {
        return ChatRequest::where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
            ->where('status', 'pending')->get()
            ->map(fn ($r) => (int) $r->sender_id === (int) $user->id ? (int) $r->receiver_id : (int) $r->sender_id)
            ->unique()->values();
    }

    /** Shared inbox filter arms so index()/conversations() can never drift apart. */
    protected function applyInboxFilter($query, string $filter, User $user): void
    {
        $requestPeerIds = $this->requestPeerIds($user);

        match ($filter) {
            'unread' => $query->whereHas('messages', fn ($q) => $q->where('sender_id', '!=', $user->id)
                ->whereDoesntHave('reads', fn ($r) => $r->where('user_id', $user->id))),
            'matches' => $query->whereNotNull('match_id'),
            'requests' => $query->whereHas('members', fn ($q) => $q->whereIn('user_id', $requestPeerIds)),
            'favorites' => $query->whereHas('members', fn ($q) => $q->whereIn('user_id', Favorite::where('user_id', $user->id)->pluck('favorited_id'))),
            'archived' => $query->whereHas('settings', fn ($q) => $q->where('user_id', $user->id)->where('is_archived', true)),
            'muted' => $query->whereHas('settings', fn ($q) => $q->where('user_id', $user->id)->where('is_muted', true)),
            'online' => $query->whereHas('members', fn ($q) => $q->where('user_id', '!=', $user->id)->whereHas('user', fn ($u) => $u->where('is_online', true))),
            'premium' => $query->whereHas('members', fn ($q) => $q->where('user_id', '!=', $user->id)->whereHas('user', fn ($u) => $u->where('is_premium', true))),
            'verified' => $query->whereHas('members', fn ($q) => $q->where('user_id', '!=', $user->id)->whereHas('user', fn ($u) => $u->where('is_verified', true))),
            'unanswered' => $query->whereHas('messages', fn ($q) => $q->where('sender_id', '!=', $user->id), '=', 1)
                ->whereDoesntHave('messages', fn ($q) => $q->where('sender_id', $user->id)),
            'recent' => $query->where('last_message_at', '>', now()->subDays(7)),
            'attachments' => $query->whereHas('messages.attachments'),
            default => null,
        };
    }

    protected function applyInboxSearch($query, ?string $search): void
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('members.user', fn ($u) => $u->where('display_name', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
    }

    public function index(Request $request, ChatService $chat)
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $query = Conversation::forUser($user->id)->with(['members.user', 'latestMessages'])->orderByDesc('last_message_at');
        $this->applyInboxFilter($query, $filter, $user);
        $this->applyInboxSearch($query, $request->query('q'));
        $conversations = $query->paginate(20);

        if ($request->wantsJson()) {
            return ConversationResource::collection($conversations)->response();
        }

        return view('member.chat.inbox', ['conversations' => $conversations, 'filter' => $filter]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);
        $conversation->load(['members.user', 'latestMessages']);

        return $request->wantsJson()
            ? response()->json(ConversationResource::make($conversation))
            : view('member.chat.show', ['conversation' => $conversation]);
    }

    public function setting(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('manage', $conversation);
        $request->validate([
            'key' => ['required', 'string', 'in:is_muted,is_pinned,is_archived,theme,nickname'],
            'value' => ['nullable'],
        ]);
        $setting = $chat->setting($conversation, $request->user(), $request->string('key'), $request->input('value'));

        return response()->json($setting);
    }

    public function markRead(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        $count = $chat->markRead($conversation, $request->user());

        return response()->json(['marked' => $count]);
    }

    public function typing(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('send', $conversation);
        $request->validate(['is_typing' => ['nullable', 'boolean']]);
        $chat->typing($conversation, $request->user(), $request->boolean('is_typing', true));

        return response()->json(['ok' => true]);
    }

    public function labels(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        $labels = $conversation->labels()->where('user_id', $request->user()->id)->get();

        return response()->json($labels);
    }

    public function addLabel(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('manage', $conversation);
        $request->validate(['label' => ['required', 'string', 'max:60'], 'color' => ['nullable', 'string', 'max:7']]);
        $label = $conversation->labels()->firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id, 'label' => $request->string('label')],
            ['color' => $request->input('color', '#888888')]
        );

        return response()->json($label, 201);
    }

    public function removeLabel(Request $request, Conversation $conversation, $labelId = null)
    {
        $this->authorize('manage', $conversation);
        $id = (int) ($labelId ?? $request->input('label_id', 0));
        abort_unless($id > 0, 422, 'label_id required.');
        $label = $conversation->labels()->where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $label->delete();

        return response()->json(['message' => 'Label removed.']);
    }

    public function conversations(Request $request, ChatService $chat)
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');
        $search = $request->query('q');
        $archived = $request->boolean('archived', false);

        $query = Conversation::forUser($user->id)->with(['members.user', 'latestMessages'])->orderByDesc('last_message_at');

        if ($archived) {
            $query->whereHas('settings', fn ($q) => $q->where('user_id', $user->id)->where('is_archived', true));
        } else {
            $this->applyInboxFilter($query, $filter, $user);
        }

        $this->applyInboxSearch($query, $search);

        $conversations = $query->paginate(20);

        return $request->wantsJson()
            ? response()->json(['conversations' => ConversationResource::collection($conversations)->response()->getData(), 'unread_total' => $chat->unreadTotal($user)])
            : view('member.chat.inbox', ['conversations' => $conversations, 'filter' => $filter]);
    }

    public function create(Request $request, ChatService $chat)
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'initial_message' => ['nullable', 'string', 'max:2000']]);
        $target = User::findOrFail($request->integer('user_id'));
        $conversation = $chat->findOrCreateDirect($request->user(), $target);

        if ($request->filled('initial_message')) {
            $message = $chat->sendMessage($conversation, $request->user(), ['body' => $request->string('initial_message')]);

            return response()->json(['conversation_id' => $conversation->id, 'message_id' => $message->id], 201);
        }

        return response()->json(['conversation_id' => $conversation->id], 201);
    }

    public function export(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        $messages = $conversation->messages()->with(['sender', 'attachments', 'reactions'])
            ->orderBy('id')->cursorPaginate(1000);

        $data = [
            'conversation' => ConversationResource::make($conversation->load(['members.user'])),
            'messages' => $messages,
            'exported_at' => now(),
            'total_messages' => $messages->total(),
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('member.chat.export', $data);
    }
}
