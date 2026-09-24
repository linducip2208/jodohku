<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PostLike;
use App\Models\UserMatch;
use App\Services\FeedService;
use App\Services\FollowService;
use App\Services\MembershipService;
use App\Services\StoryService;
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

        // Social home composition lives in services (Feed/Story/Follow),
        // never inline in Blade. Fallbacks keep the page alive on errors.
        try {
            $feed = app(FeedService::class)->feed($user, 8, (int) $request->input('page', 1));
            $feedLiked = PostLike::where('user_id', $user->id)
                ->whereIn('post_id', $feed->getCollection()->pluck('id'))->pluck('post_id')->all();
        } catch (\Throwable) {
            $feed = null;
            $feedLiked = [];
        }
        try {
            $storyTray = app(StoryService::class)->tray($user, 12);
        } catch (\Throwable) {
            $storyTray = collect();
        }
        try {
            $suggested = app(FollowService::class)->suggested($user, 6);
        } catch (\Throwable) {
            $suggested = collect();
        }

        $data = [
            'user' => UserResource::make($user),
            'stats' => ['matches' => $matchesCount, 'conversations' => $conversationsCount, 'unread' => $unreadTotal],
            'plans' => $plans,
            'feed' => $feed,
            'feedLiked' => $feedLiked,
            'storyTray' => $storyTray,
            'suggested' => $suggested,
        ];

        return $request->wantsJson() ? response()->json($data) : view('member.home', $data);
    }
}
