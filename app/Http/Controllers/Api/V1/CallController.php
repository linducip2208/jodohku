<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Conversation;
use App\Services\CallService;
use App\Services\CreditService;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function invite(Request $request, Conversation $conversation, CallService $calls, CreditService $credits)
    {
        $this->authorize('send', $conversation);
        $request->validate(['type' => ['required', 'string', 'in:voice,video']]);
        try {
            $call = $calls->invite($request->user(), $conversation, $request->string('type')->toString());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'balance' => $credits->balance($request->user())], 422);
        }

        return response()->json($call->load(['caller', 'receiver']), 201);
    }

    public function history(Request $request, Conversation $conversation, CallService $calls)
    {
        $this->authorize('view', $conversation);
        try {
            return response()->json($calls->history($conversation, $request->user(), (int) $request->query('per_page', 20)));
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function accept(Request $request, Call $call, CallService $calls)
    {
        $this->authorize('view', $call->conversation);
        try {
            return response()->json($calls->accept($call, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, Call $call, CallService $calls)
    {
        $this->authorize('view', $call->conversation);
        try {
            return response()->json($calls->reject($call, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Call $call, CallService $calls)
    {
        $this->authorize('view', $call->conversation);
        try {
            return response()->json($calls->cancel($call, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function end(Request $request, Call $call, CallService $calls)
    {
        $this->authorize('view', $call->conversation);
        try {
            return response()->json($calls->end($call, $request->user()));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function rates()
    {
        return response()->json([
            'voice_per_minute' => app(CallService::class)->ratePerMinute('voice'),
            'video_per_minute' => app(CallService::class)->ratePerMinute('video'),
            'invite_ttl_seconds' => app(CallService::class)->inviteTtlSeconds(),
        ]);
    }

    /** ICE servers for WebRTC (STUN always; TURN only when configured). */
    public function ice()
    {
        $servers = [['urls' => (string) config('jodohku.webrtc.stun', 'stun:stun.l.google.com:19302')]];
        $turn = (string) config('jodohku.webrtc.turn_url', '');
        if ($turn !== '') {
            $servers[] = [
                'urls' => $turn,
                'username' => (string) config('jodohku.webrtc.turn_username', ''),
                'credential' => (string) config('jodohku.webrtc.turn_credential', ''),
            ];
        }

        return response()->json(['iceServers' => $servers]);
    }
}
