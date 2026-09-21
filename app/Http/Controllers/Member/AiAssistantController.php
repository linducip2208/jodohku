<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AiChatAssistantService;
use App\Services\AiMatchmakerService;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function suggestedReplies(Request $request, Conversation $conversation, AiChatAssistantService $assistant)
    {
        $this->authorize('view', $conversation);
        $request->validate(['count' => ['nullable', 'integer', 'min:1', 'max:5']]);

        return response()->json([
            'replies' => $assistant->suggestedReplies($conversation, $request->user(), (int) $request->input('count', 3)),
        ]);
    }

    public function icebreakers(Request $request, User $user, AiChatAssistantService $assistant)
    {
        $this->authorize('view', $user);

        return response()->json([
            'icebreakers' => $assistant->icebreakers($request->user(), $user),
        ]);
    }

    public function rewrite(Request $request, AiChatAssistantService $assistant)
    {
        $request->validate([
            'draft' => ['required', 'string', 'max:2000'],
            'tone' => ['nullable', 'string', 'in:friendly,funny,formal,romantic,confident'],
        ]);

        return response()->json([
            'rewrite' => $assistant->rewrite($request->string('draft'), $request->input('tone', 'friendly'), $request->user()),
        ]);
    }

    public function matchmaker(Request $request, AiMatchmakerService $matchmaker)
    {
        $request->validate([
            'question' => ['nullable', 'string', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        return response()->json(
            $matchmaker->recommend($request->user(), (string) $request->input('question', ''), (int) $request->input('limit', 5))
        );
    }
}
