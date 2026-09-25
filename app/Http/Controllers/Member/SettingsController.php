<?php

namespace App\Http\Controllers\Member;

use App\Enums\PrivacyVisibility;
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
        $vis = fn ($key, $default = 'public') => in_array($request->input($key), PrivacyVisibility::values(), true)
            ? $request->input($key) : $default;
        $privacy->update([
            'show_online_status' => ! $request->boolean('hide_online'),
            'online_visibility' => $request->boolean('hide_online') ? 'private' : 'public',
            'is_incognito' => $request->boolean('incognito'),
            'show_distance' => ! $request->boolean('hide_distance'),
            'is_public_index' => $request->boolean('public_index'),
            'followers_visibility' => $vis('followers_visibility'),
            'following_visibility' => $vis('following_visibility'),
            'posts_visibility' => $vis('posts_visibility'),
            'stories_visibility' => $vis('stories_visibility', 'members_only'),
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
            'push_follows' => $request->boolean('follow'),
            'push_comments' => $request->boolean('comment'),
            'push_mentions' => $request->boolean('mention'),
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
        // GDPR-style erasure: revoke tokens/sessions, wipe contact hashes,
        // disable push tokens, anonymize PII, then soft-delete.
        try {
            $user->tokens()->delete();
        } catch (\Throwable) {
        }
        try {
            \DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable) {
        }
        try {
            $user->contactHashes()->delete();
        } catch (\Throwable) {
        }
        try {
            $user->pushTokens()->update(['disabled_at' => now()]);
        } catch (\Throwable) {
        }
        $stamp = 'deleted_'.$user->id.'_'.time();
        $user->forceFill([
            'name' => 'Deleted User',
            'display_name' => 'Deleted User',
            'email' => $stamp.'@deleted.local',
            'phone' => null,
            'phone_hash' => null,
            'username' => $stamp,
            'city' => null,
            'province' => null,
            'country' => null,
            'latitude' => null,
            'longitude' => null,
            'avatar_path' => null,
            'cover_path' => null,
            'passport_city' => null,
            'passport_province' => null,
            'passport_country' => null,
            'passport_latitude' => null,
            'passport_longitude' => null,
            'passport_active' => false,
            'referral_code' => null,
            'status' => UserStatus::Deleted,
        ])->save();
        try {
            $user->profile()->update(['headline' => null, 'bio' => null]);
        } catch (\Throwable) {
        }
        // Guard-aware logout: web session vs API token (RequestGuard has no logout()).
        try {
            $currentToken = $request->user()?->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            } else {
                auth()->logout();
            }
        } catch (\Throwable) {
            try {
                auth()->logout();
            } catch (\Throwable) {
            }
        }
        // Stateless API requests have no session store — guard it.
        if ($request->hasSession()) {
            try {
                $request->session()->invalidate();
            } catch (\Throwable) {
            }
            try {
                $request->session()->regenerateToken();
            } catch (\Throwable) {
            }
        }
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

    /** Start authenticator-app setup (TOTP). Returns secret + otpauth URL. */
    public function startTotp(Request $request, TwoFactorService $tfa)
    {
        $setup = $tfa->startTotpSetup($request->user());

        return $request->wantsJson()
            ? response()->json($setup)
            : back()->with('totp_setup', $setup);
    }

    /** Confirm authenticator setup. Returns single-use backup codes. */
    public function confirmTotp(Request $request, TwoFactorService $tfa)
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        try {
            $codes = $tfa->confirmTotpSetup($request->user(), (string) $request->input('code'));
        } catch (\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['code' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['backup_codes' => $codes])
            : back()->with('backup_codes', $codes)->with('status', 'Authenticator aktif ✅ Simpan backup codes di tempat aman.');
    }

    /** Regenerate backup codes (old ones die). */
    public function regenerateBackupCodes(Request $request, TwoFactorService $tfa)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $codes = $tfa->regenerateBackupCodes($request->user());

        return $request->wantsJson()
            ? response()->json(['backup_codes' => $codes])
            : back()->with('backup_codes', $codes)->with('status', 'Backup codes baru dibuat.');
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
