<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\VirtualConversation;
use Illuminate\Http\Request;

class ChatAdminController extends Controller
{
    public function active(Request $request)
    {
        $items = Conversation::with(['members.user'])->orderByDesc('last_message_at')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.chat.active', ['items' => $items]);
    }

    public function reported(Request $request)
    {
        return response()->json(ChatReport::with(['reporter', 'conversation'])->latest('id')->paginate(25));
    }

    public function flagged(Request $request)
    {
        $q = $request->query('q', '');
        $items = Message::where('body', 'like', "%{$q}%")->with(['sender', 'conversation'])->latest('id')->paginate(25);

        return response()->json($items);
    }

    public function virtual(Request $request)
    {
        return response()->json(VirtualConversation::with(['realUser', 'virtualProfile', 'conversation'])->latest('id')->paginate(25));
    }

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:255']]);
        $q = $request->string('q');

        return response()->json([
            'messages' => Message::where('body', 'like', "%{$q}%")->limit(20)->get(),
            'conversations' => Conversation::where('title', 'like', "%{$q}%")->limit(20)->get(),
        ]);
    }
}
