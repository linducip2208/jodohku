<?php

use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/* ---------- Landing ---------- */
Route::get('/', fn () => view('welcome'))->name('landing');

/* ---------- Auth (dating-styled) ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', function (Request $r) {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($data, $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->withInput();
        }
        $r->session()->regenerate();
        return redirect()->intended('/home');
    })->name('login.attempt');

    Route::get('/register', fn () => view('auth.register'))->name('register');
    Route::post('/register', function (Request $r) {
        $data = $r->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|unique:users,email',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female',
            'password' => 'required|min:8|confirmed',
        ]);
        $user = User::create([
            'name' => $data['name'],
            'display_name' => $data['name'],
            'email' => $data['email'],
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'password' => Hash::make($data['password']),
        ]);
        try { $user->profile()->create([]); } catch (\Throwable) {}
        Auth::login($user);
        return redirect('/home');
    })->name('register.store');

    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::post('/forgot-password', function (Request $r) {
        $r->validate(['email' => 'required|email']);
        return back()->with('status', 'Jika email terdaftar, link reset telah dikirim. Cek inbox/spam.');
    })->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset-password', ['token' => $token]))->name('password.reset');
    Route::post('/reset-password', function (Request $r) {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|min:8|confirmed', 'token' => 'required']);
        $user = User::where('email', $data['email'])->first();
        if ($user) { $user->update(['password' => Hash::make($data['password'])]); }
        return redirect()->route('login')->with('status', 'Password diperbarui. Silakan masuk.');
    })->name('password.update');
});

Route::get('/verify-email', fn () => view('auth.verify-email'))->name('verification.notice');
Route::post('/verify-email/send', fn () => back()->with('status', 'Link verifikasi dikirim ulang.'))->name('verification.send');
Route::get('/phone-verify', fn () => view('auth.phone-verify'))->name('phone.verify');
Route::post('/phone-verify/send', fn () => back()->with('status', 'Kode OTP dikirim ulang via SMS/WhatsApp.'))->name('phone.send');
Route::post('/phone-verify', function (Request $r) {
    $r->validate(['code' => 'required|string|max:6']);
    if (Auth::check()) { try { Auth::user()->update(['phone_verified_at' => now()]); } catch (\Throwable) {} }
    return redirect('/home');
});
Route::post('/logout', function (Request $r) {
    Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken();
    return redirect('/');
})->name('logout');

/* ---------- Member ---------- */
Route::middleware('auth')->group(function () {
    Route::get('/home', fn () => view('member.home'))->name('member.home');
    Route::get('/discover', fn () => view('member.discover'))->name('member.discover');
    Route::get('/profile/{user}', function (User $user) {
        $score = null;
        try {
            $me = Auth::user();
            if ($me && $me->id !== $user->id) {
                $score = app(\App\Services\MatchingEngine::class)->scorePair($me, $user)['mutual'] ?? null;
            }
        } catch (\Throwable) {}
        return view('member.profile.show', ['profileUser' => $user->loadMissing(['profile', 'photos', 'interests']), 'score' => $score]);
    })->name('member.profile');
    Route::get('/matches', fn () => view('member.matches'))->name('member.matches');
    Route::get('/likes', fn () => view('member.likes'))->name('member.likes');
    Route::get('/visitors', fn () => view('member.visitors'))->name('member.visitors');
    Route::get('/favorites', fn () => view('member.favorites'))->name('member.favorites');

    Route::get('/chat', fn () => view('member.chat.inbox'))->name('member.chat');
    Route::get('/chat/{conversation}', function (Conversation $conversation) {
        abort_unless($conversation->involves(Auth::id()), 403);
        return view('member.chat.show', ['conversation' => $conversation]);
    })->name('member.chat.show');
    Route::post('/chat/{conversation}/pin', function (Conversation $conversation, \App\Services\ChatService $chat) {
        $chat->setting($conversation, Auth::user(), 'pinned', true);
        return back();
    });
    Route::post('/chat/{conversation}/mute', function (Conversation $conversation, \App\Services\ChatService $chat) {
        $chat->setting($conversation, Auth::user(), 'muted', true);
        return back();
    });
    Route::post('/chat/{conversation}/archive', function (Conversation $conversation, \App\Services\ChatService $chat) {
        $chat->setting($conversation, Auth::user(), 'archived', true);
        return back();
    });
    Route::post('/chat/{conversation}/unmatch', function (Conversation $conversation) {
        try { $conversation->update(['is_blocked' => true]); } catch (\Throwable) {}
        return redirect('/chat')->with('status', 'Unmatch berhasil.');
    });

    Route::get('/notifications', fn () => view('member.notifications.center'))->name('member.notifications');
    Route::post('/notifications/read-all', function (\App\Services\NotificationService $svc) {
        $svc->markAllRead(Auth::user());
        return back();
    });

    Route::get('/premium', fn () => view('member.premium.plans'))->name('member.premium');
    Route::post('/premium/checkout', function (Request $r, \App\Services\PaymentService $pay) {
        $plan = $r->input('plan', 'premium_monthly');
        try {
            $res = $pay->checkout(Auth::user(), ['items' => [['type' => 'plan', 'code' => $plan]]]);
            return redirect($res['redirect_url'] ?? '/premium')->with('status', 'Checkout dibuat.');
        } catch (\Throwable $e) { return back()->with('status', $e->getMessage()); }
    });

    Route::get('/credits', fn () => view('member.credits.wallet'))->name('member.credits');
    Route::post('/credits/checkout', function (Request $r, \App\Services\PaymentService $pay) {
        try {
            $res = $pay->checkout(Auth::user(), ['items' => [['type' => 'credits', 'code' => $r->input('product')]]]);
            return redirect($res['redirect_url'] ?? '/credits')->with('status', 'Checkout kredit dibuat.');
        } catch (\Throwable $e) { return back()->with('status', $e->getMessage()); }
    });

    Route::get('/verification', fn () => view('member.verification.form'))->name('member.verification');
    Route::post('/verification', function (Request $r, \App\Services\VerificationService $svc) {
        $data = $r->validate(['type' => 'required|string', 'notes' => 'nullable|string']);
        try {
            $svc->submit(Auth::user(), $data['type'], [], $data['notes'] ?? null);
            return back()->with('status', 'Pengajuan verifikasi terkirim. Pantau status di bawah.');
        } catch (\Throwable $e) { return back()->with('status', $e->getMessage()); }
    });

    Route::get('/settings', fn () => view('member.settings.index'))->name('member.settings');
    Route::post('/settings/profile', function (Request $r) {
        $u = Auth::user();
        $u->update($r->only(['display_name', 'city']));
        try { $u->profile()->updateOrCreate([], $r->only(['bio', 'occupation', 'education'])); } catch (\Throwable) {}
        return back()->with('status', 'Profil disimpan ✅');
    });
    Route::post('/settings/privacy', fn () => back()->with('status', 'Preferensi privasi disimpan ✅'));
    Route::post('/settings/notifications', fn () => back()->with('status', 'Preferensi notifikasi disimpan ✅'));

    Route::get('/safety', fn () => view('member.safety.center'))->name('member.safety');
    Route::post('/safety/block', function (Request $r) {
        $id = (int) $r->input('user_id');
        try { \App\Models\Block::firstOrCreate(['blocker_id' => Auth::id(), 'blocked_id' => $id]); } catch (\Throwable) {}
        return back()->with('status', 'User diblokir ⛔');
    });
    Route::post('/safety/unblock', function (Request $r) {
        try { \App\Models\Block::where('blocker_id', Auth::id())->where('blocked_id', (int) $r->input('user_id'))->delete(); } catch (\Throwable) {}
        return back()->with('status', 'Blokir dibuka.');
    });
    Route::post('/safety/report', function (Request $r) {
        $r->validate(['user_id' => 'required']);
        try {
            \App\Models\Report::create(['reporter_id' => Auth::id(), 'reported_user_id' => (int) $r->input('user_id'), 'reason' => $r->input('reason', 'Lainnya'), 'details' => $r->input('details'), 'status' => 'open']);
        } catch (\Throwable) {}
        return back()->with('status', 'Laporan terkirim. Tim moderasi meninjau 🚩');
    });

    Route::get('/gifts', fn () => view('member.gifts.index'))->name('member.gifts');
    Route::post('/gifts/send', function (Request $r, \App\Services\GiftService $gifts) {
        $r->validate(['gift' => 'required', 'receiver_id' => 'required|integer']);
        try {
            $gifts->send(Auth::user(), User::findOrFail((int) $r->input('receiver_id')), $r->input('gift'));
            return back()->with('status', 'Gift terkirim 🎁');
        } catch (\Throwable $e) { return back()->with('status', $e->getMessage()); }
    });

    Route::get('/boosts', fn () => view('member.boosts.index'))->name('member.boosts');
    Route::post('/boosts/activate', function (\App\Services\BoostService $boosts) {
        try { $boosts->activate(Auth::user(), 30); return back()->with('status', 'Boost aktif 30 menit 🚀'); }
        catch (\Throwable $e) { return back()->with('status', $e->getMessage()); }
    });

    Route::get('/events', fn () => view('member.events.index'))->name('member.events');
    Route::get('/events/{event}', function (Event $event) {
        return view('member.events.show', ['event' => $event]);
    })->name('member.events.show');
    Route::post('/events/{id}/rsvp', function (int $id) {
        try { \App\Models\EventMember::firstOrCreate(['event_id' => $id, 'user_id' => Auth::id()]); } catch (\Throwable) {}
        return back()->with('status', 'RSVP berhasil 🎟️ Sampai jumpa di lokasi!');
    });

    Route::get('/blog', fn () => view('member.blog.index'))->name('member.blog');
    Route::get('/blog/{slug}', fn (string $slug) => view('member.blog.show', ['slug' => $slug]))->name('member.blog.show');
    Route::get('/forums', fn () => view('member.forums.index'))->name('member.forums');
    Route::get('/forums/{slug}', fn (string $slug) => view('member.forums.threads', ['slug' => $slug]))->name('member.forums.threads');
    Route::get('/forums/thread/{thread}', fn (int $thread) => view('member.forums.thread', ['threadId' => $thread]))->name('member.forums.thread');
    Route::post('/forums/{slug}/threads', [\App\Http\Controllers\Member\ForumController::class, 'storeThread'])->name('member.forums.threads.store');
    Route::post('/forums/thread/{thread}/reply', [\App\Http\Controllers\Member\ForumController::class, 'reply'])->name('member.forums.thread.reply');
});

/* ---------- Admin (HTML views; admin.php holds controller/JSON routes — only non-duplicate URIs here) ---------- */
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    foreach (['profiles' => 'admin.profiles', 'photos' => 'admin.photos', 'verification' => 'admin.verification', 'reports' => 'admin.reports', 'blocks' => 'admin.blocks'] as $uri => $view) {
        Route::get('/' . $uri, fn () => view($view))->name(str_replace('.', '-', $uri));
    }
    Route::post('/verification/{id}/approve', function (int $id, \App\Services\VerificationService $svc) {
        $req = VerificationRequest::findOrFail($id);
        $svc->approve($req, Auth::user());
        return back();
    });
    Route::post('/verification/{id}/reject', function (int $id, \App\Services\VerificationService $svc) {
        $req = VerificationRequest::findOrFail($id);
        $svc->reject($req, Auth::user(), 'Tidak memenuhi syarat');
        return back();
    });
    Route::get('/matching/options', fn () => view('admin.matching.options'))->name('matching-options');
    foreach (['conversations' => 'admin.chat.conversations', 'messages' => 'admin.chat.messages', 'requests' => 'admin.chat.requests', 'operators' => 'admin.chat.operators', 'virtual' => 'admin.chat.virtual'] as $uri => $view) {
        Route::get('/chat/' . $uri, fn () => view($view))->name('chat-' . $uri);
    }
    foreach (['plans' => 'admin.membership.plans', 'subscriptions' => 'admin.membership.subscriptions', 'credits' => 'admin.membership.credits', 'products' => 'admin.membership.products', 'payments' => 'admin.membership.payments', 'gateways' => 'admin.membership.gateways', 'coupons' => 'admin.membership.coupons'] as $uri => $view) {
        Route::get('/membership/' . $uri, fn () => view($view))->name('membership-' . $uri);
    }
    foreach (['comments' => 'admin.community.comments', 'forums' => 'admin.community.forums', 'blogs' => 'admin.community.blogs'] as $uri => $view) {
        Route::get('/community/' . $uri, fn () => view($view))->name('community-' . $uri);
    }
    Route::get('/notifications', fn () => view('admin.notifications'))->name('notifications');
    Route::post('/notifications/send', fn (Request $r) => back()->with('status', 'Broadcast dikirim ke segmen.'));
    Route::get('/email', fn () => view('admin.email'))->name('email');
    Route::get('/feature-flags', fn () => view('admin.feature-flags'))->name('feature-flags');
    Route::get('/audit-logs', fn () => view('admin.audit-logs'))->name('audit-logs');
});
