<?php

namespace App\Http\Middleware;

use App\Models\Block;
use App\Models\ChatBlock;
use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $targetId = $request->route('user') ?? $request->route('id') ?? $request->input('reported_user_id') ?? $request->input('user_id');

        if ($user && $targetId && is_numeric($targetId)) {
            if (Block::existsBetween((int) $user->id, (int) $targetId)) {
                abort(403, 'Interaction blocked.');
            }
            $chatBlocked = ChatBlock::where(
                fn ($q) => $q->where('blocker_id', $user->id)->where('blocked_id', $targetId)
            )->orWhere(
                fn ($q) => $q->where('blocker_id', $targetId)->where('blocked_id', $user->id)
            )->exists();
            if ($chatBlocked) {
                abort(403, 'Conversation blocked.');
            }
        }

        if ($user && $request->route('conversation')) {
            $conversation = $request->route('conversation');
            if (is_numeric($conversation)) {
                $conversation = Conversation::find($conversation);
            }
            if ($conversation && method_exists($conversation, 'otherMember')) {
                $other = $conversation->otherMember((int) $user->id);
                if ($other && Block::existsBetween((int) $user->id, (int) $other->user_id)) {
                    abort(403, 'Conversation blocked.');
                }
            }
        }

        return $next($request);
    }
}
