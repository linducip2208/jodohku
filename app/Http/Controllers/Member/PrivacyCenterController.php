<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Mute;
use App\Models\Post;
use App\Models\UserMatch;
use App\Services\ContactBlockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/**
 * Privacy Center: one place for visibility, blocks/mutes, contact
 * blocking, sessions, export, pause, and deletion entry points.
 * Every action reuses existing services/routes — this only composes.
 */
class PrivacyCenterController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $blocks = Block::with('blocked:id,display_name,name')->where('blocker_id', $user->id)->latest('id')->limit(100)->get();
        $mutes = Mute::with('muted:id,display_name,name')->where('muter_id', $user->id)->latest('id')->limit(100)->get();
        $sessions = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->limit(20)->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return view('member.privacy.index', [
            'blocks' => $blocks,
            'mutes' => $mutes,
            'sessions' => $sessions,
            'contactStatus' => app(ContactBlockService::class)->status($user),
        ]);
    }

    public function revokeSession(Request $request, string $id)
    {
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $id)->delete();

        return back()->with('status', 'Sesi dicabut. Perangkat itu harus login ulang.');
    }

    public function pause(Request $request)
    {
        $request->user()->update(['is_paused' => true]);

        return back()->with('status', 'Akun dijeda: profilmu tak lagi muncul di discovery/feed/pencarian.');
    }

    public function resume(Request $request)
    {
        $request->user()->update(['is_paused' => false]);

        return back()->with('status', 'Akun aktif kembali.');
    }

    /** Privacy-conscious self export (no passwords, tokens, documents). */
    public function export(Request $request)
    {
        $user = $request->user()->load(['profile', 'partnerPreference', 'interests', 'photos']);
        $payload = [
            'exported_at' => now()->toISOString(),
            'user' => $user->only(['id', 'name', 'email', 'phone', 'username', 'display_name', 'date_of_birth', 'gender', 'city', 'province', 'country', 'is_verified', 'is_premium', 'created_at']),
            'profile' => $user->profile?->only(['headline', 'bio', 'occupation', 'education', 'religion', 'height_cm', 'relationship_goal', 'interests']),
            'preferences' => $user->partnerPreference?->toArray(),
            'photos' => $user->photos->map(fn ($p) => $p->only(['path', 'status', 'is_private', 'created_at']))->values(),
            'counts' => [
                'follows_given' => $user->followsGiven()->count(),
                'followers' => $user->followsReceived()->count(),
                'posts' => Post::where('user_id', $user->id)->count(),
                'matches' => UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))->where('is_active', true)->count(),
            ],
        ];

        return Response::json($payload, 200, ['Content-Disposition' => 'attachment; filename="jodohku-export-'.$user->id.'.json"']);
    }
}
