<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChatRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\ChatRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function conversations(Request $request)
    {
        $items = Conversation::forUser($request->user()->id)->with(['members.user', 'latestMessages'])
            ->orderByDesc('last_message_at')->paginate(20);

        return ConversationResource::collection($items)->response();
    }

    public function overview(Request $request, ChatService $chat)
    {
        return response()->json($chat->overview($request->user()));
    }

    public function search(Request $request, ChatService $chat)
    {
        $request->validate(['q' => ['required', 'string', 'max:255']]);

        return response()->json($chat->searchConversations($request->user(), $request->string('q')));
    }

    public function stats(Request $request, Conversation $conversation, ChatService $chat)
    {
        try {
            return response()->json($chat->conversationStats($conversation, $request->user()));
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function clearHistory(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        $chat->clearHistory($conversation, $request->user());

        return response()->json(['message' => 'History cleared.']);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        return response()->json(ConversationResource::make($conversation->load(['members.user'])));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);
        $items = Message::where('conversation_id', $conversation->id)->with(['sender', 'attachments', 'reactions'])
            ->orderByDesc('id')->cursorPaginate(30);

        return MessageResource::collection($items)->response();
    }

    public function send(SendMessageRequest $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('send', $conversation);

        try {
            $message = $chat->sendMessage($conversation, $request->user(), $request->validated(), $request->input('client_message_id'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(MessageResource::make($message->load(['sender', 'attachments'])), 201);
    }

    public function upload(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('send', $conversation);
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ]);
        try {
            $message = $chat->sendAttachment(
                $conversation, $request->user(), $request->file('file'),
                $request->input('body'), $request->input('client_message_id')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(MessageResource::make($message->load(['sender', 'attachments'])), 201);
    }

    public function edit(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('update', $message);
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return response()->json(MessageResource::make($chat->editMessage($message, $request->user(), $request->string('body'))));
    }

    public function delete(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('delete', $message);
        $chat->deleteMessage($message, $request->user(), $request->input('scope', 'for_me'));

        return response()->json(['message' => 'Deleted.']);
    }

    public function read(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);

        return response()->json(['marked' => $chat->markRead($conversation, $request->user())]);
    }

    public function requests(Request $request)
    {
        $user = $request->user();

        return response()->json(ChatRequest::where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))->latest('id')->paginate(20));
    }

    public function requestAction(Request $request, ChatRequest $chatRequest, ChatService $chat)
    {
        $request->validate(['action' => ['required', 'string', 'in:accept,decline,cancel']]);
        $user = $request->user();
        $action = $request->string('action')->toString();

        if ($chatRequest->isExpired() && $chatRequest->status === ChatRequestStatus::Pending) {
            $chatRequest->update(['status' => ChatRequestStatus::Expired, 'responded_at' => now()]);
        }
        if ($chatRequest->status->isFinal()) {
            return response()->json(['message' => 'Request is no longer pending.'], 422);
        }

        if ($action === 'cancel' && (int) $chatRequest->sender_id === (int) $user->id) {
            $chatRequest->update(['status' => 'cancelled', 'responded_at' => now()]);

            return response()->json(['message' => 'Cancelled.']);
        }
        if ((int) $chatRequest->receiver_id !== (int) $user->id) {
            abort(403);
        }
        if ($action === 'accept') {
            $chatRequest->accept();
            $conversation = $chat->findOrCreateDirect($chatRequest->sender, $user);

            return response()->json(['conversation_id' => $conversation->id]);
        }
        $chatRequest->decline();

        return response()->json(['message' => 'Declined.']);
    }

    public function reactions(Request $request, Message $message)
    {
        $this->authorize('view', $message->conversation);

        return response()->json($message->reactions()->with('user')->get()->groupBy('emoji')->map(fn ($g) => [
            'emoji' => $g->first()->emoji,
            'count' => $g->count(),
            'users' => $g->pluck('user')->filter()->values(),
        ])->values());
    }

    public function create(Request $request, ChatService $chat)
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'initial_message' => ['nullable', 'string', 'max:2000']]);
        $target = User::findOrFail($request->integer('user_id'));
        $conversation = $chat->findOrCreateDirect($request->user(), $target);

        if ($request->filled('initial_message')) {
            $message = $chat->sendMessage($conversation, $request->user(), ['body' => $request->string('initial_message')]);

            return response()->json(['conversation_id' => $conversation->id, 'message' => MessageResource::make($message->load(['sender', 'attachments']))], 201);
        }

        return response()->json(['conversation_id' => $conversation->id], 201);
    }

    public function forward(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('view', $message->conversation);
        $request->validate(['conversation_id' => ['required', 'integer', 'exists:conversations,id']]);
        $target = Conversation::findOrFail($request->integer('conversation_id'));
        $this->authorize('send', $target);

        $forwarded = $chat->sendMessage($target, $request->user(), [
            'body' => $message->body,
            'type' => $message->type,
            'metadata' => ['forwarded_from' => $message->conversation->id, 'original_message_id' => $message->id],
            'attachments' => $message->attachments->map(fn ($a) => [
                'file_path' => $a->file_path,
                'file_name' => $a->file_name,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'width' => $a->width,
                'height' => $a->height,
                'duration_seconds' => $a->duration_seconds,
            ])->all(),
        ]);

        return response()->json(MessageResource::make($forwarded->load(['sender', 'attachments'])), 201);
    }

    public function react(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('view', $message->conversation);
        $request->validate(['emoji' => ['required', 'string', 'max:20']]);
        try {
            $reaction = $chat->react($message, $request->user(), $request->string('emoji')->toString());
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return response()->json($reaction, 201);
    }

    public function unreact(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('view', $message->conversation);
        $request->validate(['emoji' => ['required', 'string', 'max:20']]);
        $chat->unreact($message, $request->user(), $request->string('emoji')->toString());

        return response()->json(['message' => 'Removed.']);
    }

    public function typing(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('send', $conversation);
        $request->validate(['is_typing' => ['nullable', 'boolean']]);
        try {
            $chat->typing($conversation, $request->user(), $request->boolean('is_typing', true));
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function setting(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('manage', $conversation);
        $request->validate([
            'key' => ['required', 'string', 'in:is_muted,is_pinned,is_archived,theme,nickname'],
            'value' => ['nullable'],
        ]);
        try {
            $setting = $chat->setting($conversation, $request->user(), $request->string('key')->toString(), $request->input('value'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($setting);
    }

    public function searchInConversation(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        $request->validate(['q' => ['required', 'string', 'max:255']]);
        try {
            $items = $chat->search($conversation, $request->user(), $request->string('q')->toString());
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return response()->json(MessageResource::collection($items));
    }

    public function export(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        try {
            return response()->json($chat->export($conversation, $request->user()));
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function labels(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        return response()->json($conversation->labels()->where('user_id', $request->user()->id)->get());
    }

    public function addLabel(Request $request, Conversation $conversation)
    {
        $this->authorize('manage', $conversation);
        $request->validate(['label' => ['required', 'string', 'max:60'], 'color' => ['nullable', 'string', 'max:7']]);
        $label = $conversation->labels()->firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id, 'label' => $request->string('label')->toString()],
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

    public function markAllRead(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);

        return response()->json(['marked' => $chat->markAllRead($request->user())]);
    }
}
