<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\UserMatch;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Incremental sync for Flutter (battery/data friendly): everything the
 * user missed since a timestamp, capped per section. Window max 7 days
 * (older → full refetch via the dedicated endpoints).
 */
class SyncController extends Controller
{
    public function delta(Request $request)
    {
        // Tolerant parsing (Flutter clocks/encodings vary): garbage falls
        // back to the default 24h window instead of 422.
        $since = now()->subDay();
        if ($request->filled('since')) {
            try {
                $since = max(Carbon::parse($request->input('since')), now()->subDays(7));
            } catch (\Throwable) {
            }
        }
        $me = $request->user()->id;

        $matches = UserMatch::where(fn ($q) => $q->where('user_a_id', $me)->orWhere('user_b_id', $me))
            ->where('updated_at', '>', $since)
            ->latest('updated_at')->limit(50)->get();

        $convIds = ConversationMember::where('user_id', $me)->pluck('conversation_id');
        $messages = Message::whereIn('conversation_id', $convIds)
            ->where('created_at', '>', $since)
            ->latest('id')->limit(100)->get();

        $notifications = $request->user()->notifications()
            ->where('created_at', '>', $since)
            ->latest('created_at')->limit(50)->get();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'since' => $since->toIso8601String(),
            'matches' => $matches,
            'messages' => $messages,
            'notifications' => $notifications,
        ]);
    }
}
