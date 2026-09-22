<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserMatch;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load(['profile', 'photos']);
        $matchesCount = UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->where('is_active', true)->count();
        $conversationsCount = Conversation::forUser($user->id)->count();
        $unreadTotal = Message::whereIn('conversation_id', Conversation::forUser($user->id)->pluck('id'))
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))->count();
        $plans = app(MembershipService::class)->plans();

        $data = [
            'user' => UserResource::make($user),
            'stats' => ['matches' => $matchesCount, 'conversations' => $conversationsCount, 'unread' => $unreadTotal],
            'plans' => $plans,
        ];

        return $request->wantsJson() ? response()->json($data) : view('member.home', $data);
    }
}
