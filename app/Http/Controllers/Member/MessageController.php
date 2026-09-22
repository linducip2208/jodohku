<?php

namespace App\Http\Controllers\Member;

use App\Exceptions\ChatQuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);
        $messages = Message::where('conversation_id', $conversation->id)
            ->with(['sender', 'attachments', 'reactions'])
            ->orderByDesc('id')->cursorPaginate(30);

        return MessageResource::collection($messages)->response();
    }

    public function store(SendMessageRequest $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('send', $conversation);
        try {
            $message = $chat->sendMessage($conversation, $request->user(), $request->validated(), $request->input('client_message_id'));
        } catch (ChatQuotaExceededException $e) {
            return response()->json(['message' => $e->getMessage(), 'upgrade' => ! $e->isPremium, 'limit' => $e->limit], 429);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $message->load(['sender', 'attachments']);

        return response()->json(MessageResource::make($message), 201);
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
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['file' => $e->getMessage()]);
        }
        $message->load(['sender', 'attachments']);

        return $request->wantsJson()
            ? response()->json(MessageResource::make($message), 201)
            : back()->with('status', 'Lampiran terkirim ✅');
    }

    public function update(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('update', $message);
        $request->validate(['body' => ['required', 'string', 'max:2000']]);
        try {
            $message = $chat->editMessage($message, $request->user(), $request->string('body'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(MessageResource::make($message));
    }

    public function destroy(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('delete', $message);
        $request->validate(['scope' => ['nullable', 'string', 'in:for_me,for_everyone']]);
        $chat->deleteMessage($message, $request->user(), $request->input('scope', 'for_me'));

        return response()->json(['message' => 'Deleted.']);
    }

    public function react(Request $request, Message $message, ChatService $chat)
    {
        $this->authorize('view', $message);
        $request->validate(['emoji' => ['required', 'string', 'max:20']]);
        $reaction = $chat->react($message, $request->user(), $request->string('emoji'));

        return response()->json($reaction, 201);
    }

    public function search(Request $request, Conversation $conversation, ChatService $chat)
    {
        $this->authorize('view', $conversation);
        $request->validate(['q' => ['required', 'string', 'max:255']]);
        $results = $chat->search($conversation, $request->user(), $request->string('q'));

        return MessageResource::collection($results)->response();
    }
}
