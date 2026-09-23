<?php

namespace App\Http\Controllers\Member;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationPreference;
use App\Models\ProfilePrivacy;
use App\Services\AuditService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load(['notificationPreference', 'profilePrivacy']);

        return $request->wantsJson()
            ? response()->json($user)
            : view('member.settings.index', ['user' => $user]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $request->user()->update($request->only(['name', 'email', 'phone']));

        return response()->json($request->user()->fresh());
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);
        $request->user()->update(['password' => Hash::make($request->string('password'))]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function privacy(Request $request)
    {
        $user = $request->user();
        $privacy = ProfilePrivacy::firstOrCreate(['user_id' => $user->id]);
        $privacy->update([
            'show_online_status' => ! $request->boolean('hide_online'),
            'online_visibility' => $request->boolean('hide_online') ? 'private' : 'public',
            'is_incognito' => $request->boolean('incognito'),
            'show_distance' => ! $request->boolean('hide_distance'),
            'is_public_index' => $request->boolean('public_index'),
        ]);

        return $request->wantsJson()
            ? response()->json($privacy->fresh())
            : back()->with('status', 'Preferensi privasi disimpan ✅');
    }

    public function notifications(Request $request)
    {
        $prefs = NotificationPreference::firstOrCreate(['user_id' => $request->user()->id]);
        $prefs->update([
            'email_matches' => $request->boolean('match'),
            'push_matches' => $request->boolean('match'),
            'email_messages' => $request->boolean('message'),
            'push_messages' => $request->boolean('message'),
            'push_likes' => $request->boolean('like'),
        ]);

        return $request->wantsJson()
            ? response()->json($prefs->fresh())
            : back()->with('status', 'Preferensi notifikasi disimpan ✅');
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        try {
            app(AuditService::class)->log('account.deleted', $user, $user);
        } catch (\Throwable) {
        }
        $user->tokens()->delete();
        $user->update(['status' => UserStatus::Deleted]);
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $user->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Account deleted.'])
            : redirect('/')->with('status', 'Akun dihapus permanen. Kami akan merindukanmu.');
    }

    public function enable2fa(Request $request, TwoFactorService $tfa)
    {
        $user = $request->user();
        $tfa->enable($user);
        try {
            $tfa->sendChallenge($user);
        } catch (\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 429)
                : back()->with('status', $e->getMessage());
        }

        return $request->wantsJson()
            ? response()->json(['message' => '2FA enabled. Verification code sent to your email.'])
            : back()->with('status', '2FA aktif. Kode verifikasi dikirim ke email ✅');
    }

    public function disable2fa(Request $request, TwoFactorService $tfa)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $tfa->disable($request->user());

        return $request->wantsJson()
            ? response()->json(['message' => '2FA disabled.'])
            : back()->with('status', '2FA dinonaktifkan.');
    }

    public function loginHistory(Request $request)
    {
        $logs = AuditLog::where('actor_id', $request->user()->id)
            ->whereIn('action', ['auth.login', 'auth.login.api', 'admin.login'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($logs);
    }

    public function sessions(Request $request)
    {
        $tokens = $request->user()->tokens()->latest('created_at')->paginate(20);
        $sessions = $tokens->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'created_at' => $t->created_at,
            'last_used' => $t->last_used_at,
            'expires_at' => $t->expires_at,
            'ip' => $t->last_used_ip ?? $t->ip_address ?? null,
            'user_agent' => $t->user_agent ?? null,
            'is_current' => $t->id === $request->user()->currentAccessToken()?->id,
        ]);

        return response()->json(['sessions' => $sessions, 'total' => $tokens->total()]);
    }
}
