<?php

use App\Enums\EventStatus;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Member\BiroJodohController;
use App\Http\Controllers\Member\ChatController;
use App\Http\Controllers\Member\ChatRequestController;
use App\Http\Controllers\Member\EventController;
use App\Http\Controllers\Member\ForumController;
use App\Http\Controllers\Member\MatchController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\QuestionnaireController;
use App\Http\Controllers\Member\SafetyController;
use App\Http\Controllers\Member\SettingsController;
use App\Models\Block;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Forum;
use App\Models\Report;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\BoostService;
use App\Services\ChatService;
use App\Services\GiftService;
use App\Services\MatchingEngine;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\TwoFactorService;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/* ---------- Landing ---------- */
Route::get('/', fn () => view('welcome'))->name('landing');

/* ---------- Health (public, no secrets/internals) ---------- */
Route::get('/health', function () {
    $database = 'ok';
    try {
        DB::connection()->getPdo();
    } catch (Throwable) {
        $database = 'down';
    }

    return response()->json([
        'status' => $database === 'ok' ? 'ok' : 'degraded',
        'database' => $database,
        'time' => now()->toIso8601String(),
    ], $database === 'ok' ? 200 : 503);
})->name('health');
Route::get('/privacy', fn () => view('landing.privacy'))->name('legal.privacy');
Route::get('/terms', fn () => view('landing.terms'))->name('legal.terms');
Route::get('/contact', fn () => view('landing.contact'))->name('contact');
Route::post('/contact', function (Request $r) {
    $data = $r->validate([
        'name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'max:190'],
        'topic' => ['required', 'string', 'in:akun,pembayaran,moderasi,keamanan,kerjasama,lainnya'],
        'message' => ['required', 'string', 'max:5000'],
    ]);
    ContactMessage::create($data + ['ip' => $r->ip()]);

    return back()->with('status', 'Pesan terkirim. Tim kami akan membalas via email maksimal 2x24 jam.');
})->middleware('throttle:5,1,contact')->name('contact.store');

Route::get('/sitemap.xml', function () {
    $urls = [['loc' => url('/'), 'updated' => now()->toAtomString(), 'freq' => 'daily']];
    try {
        foreach (BlogPost::published()->latest('published_at')->take(200)->get(['slug', 'updated_at']) as $p) {
            $urls[] = ['loc' => url('/blog/'.$p->slug), 'updated' => $p->updated_at?->toAtomString() ?? now()->toAtomString(), 'freq' => 'weekly'];
        }
        $urls[] = ['loc' => url('/blog'), 'updated' => now()->toAtomString(), 'freq' => 'daily'];
        foreach (Forum::where('is_active', true)->get(['slug', 'updated_at']) as $f) {
            $urls[] = ['loc' => url('/forums/'.$f->slug), 'updated' => $f->updated_at?->toAtomString() ?? now()->toAtomString(), 'freq' => 'daily'];
        }
        $urls[] = ['loc' => url('/forums'), 'updated' => now()->toAtomString(), 'freq' => 'daily'];
    } catch (Throwable) {
    }
    $xml = view('seo.sitemap', ['urls' => $urls])->render();

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

/* ---------- Auth (dating-styled) ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', function (Request $r) {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt($data, $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->withInput();
        }
        $user = Auth::user();
        if ($reason = $user->loginBlockedReason()) {
            Auth::logout();

            return back()->withErrors(['email' => $reason])->withInput();
        }
        if ($user->two_factor_enabled) {
            try {
                app(TwoFactorService::class)->sendChallenge($user);
            } catch (RuntimeException) {
            }
            $r->session()->put('2fa_pending_id', $user->id);
            Auth::logout();

            return redirect()->route('2fa.challenge');
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
        try {
            $user->profile()->create([]);
        } catch (Throwable) {
        }
        Auth::login($user);

        return redirect('/home');
    })->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:3,1,web-password')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::get('/verify-email', fn () => view('auth.verify-email'))->name('verification.notice');
Route::post('/verify-email/send', [VerificationController::class, 'resend'])->middleware('throttle:3,1,email-verify')->name('verification.send');
Route::get('/verify-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:10,1,email-verify-click'])->name('verification.verify');
Route::get('/phone-verify', fn () => view('auth.phone-verify'))->name('phone.verify');
Route::post('/phone-verify/send', [PhoneVerificationController::class, 'send'])->middleware('throttle:5,1,phone-otp')->name('phone.send');
Route::post('/phone-verify', [PhoneVerificationController::class, 'verify'])->name('phone.verify.store');
Route::post('/logout', function (Request $r) {
    Auth::logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return redirect('/');
})->name('logout');

/* ---------- Two-factor challenge (email OTP) ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/2fa', function () {
        return session()->has('2fa_pending_id')
            ? view('auth.two-factor')
            : redirect()->route('login');
    })->name('2fa.challenge');
    Route::post('/2fa', function (Request $r) {
        $r->validate(['code' => ['required', 'string', 'max:6']]);
        $user = User::find($r->session()->get('2fa_pending_id'));
        if (! $user) {
            return redirect()->route('login');
        }
        $tfa = app(TwoFactorService::class);
        try {
            $ok = $tfa->verify($user, (string) $r->input('code'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }
        if (! $ok) {
            return back()->withErrors(['code' => 'Kode salah.']);
        }
        $r->session()->forget('2fa_pending_id');
        Auth::login($user, true);
        $r->session()->regenerate();

        return redirect()->intended('/home');
    })->name('2fa.verify')->middleware('throttle:10,1,web-2fa');
    Route::post('/2fa/resend', function (Request $r) {
        $user = User::find($r->session()->get('2fa_pending_id'));
        if (! $user) {
            return redirect()->route('login');
        }
        try {
            app(TwoFactorService::class)->sendChallenge($user);
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'Kode baru dikirim ke email.');
    })->name('2fa.resend')->middleware('throttle:3,1,web-2fa-resend');
});

/* ---------- Member ---------- */
Route::middleware(['auth', 'active.account'])->group(function () {
    Route::get('/home', fn () => view('member.home'))->name('member.home');
    Route::get('/discover', fn () => view('member.discover'))->name('member.discover');
    Route::get('/profile/edit', function () {
        return view('member.profile.edit', ['user' => Auth::user()->load(['profile', 'photos', 'interests'])]);
    })->name('member.profile.edit');
    Route::get('/profile/{user}', function (User $user) {
        $score = null;
        try {
            $me = Auth::user();
            if ($me && $me->id !== $user->id) {
                $score = app(MatchingEngine::class)->scorePair($me, $user)['mutual'] ?? null;
            }
        } catch (Throwable) {
        }
        $me = Auth::user();
        $isSelf = $me && (int) $me->id === (int) $user->id;
        $user->loadMissing([
            'profile', 'interests',
            'photos' => fn ($q) => $q->ordered()->when(! ($isSelf || ($me && $me->isStaff())), fn ($qq) => $qq->where('status', 'approved')),
        ]);

        return view('member.profile.show', ['profileUser' => $user, 'score' => $score]);
    })->name('member.profile');
    Route::get('/profile/{user}/view-history', [ProfileController::class, 'viewHistory'])->name('member.profile.view-history');
    Route::get('/profile/{user}/viewers', [ProfileController::class, 'viewers'])->name('member.profile.viewers');
    Route::get('/profile/{user}/visible-to', [ProfileController::class, 'visibleTo'])->name('member.profile.visible-to');
    Route::get('/profile/{user}/stats', [ProfileController::class, 'stats'])->name('member.profile.stats');
    Route::get('/profile/blocking', [ProfileController::class, 'blocking'])->name('member.profile.blocking');
    Route::get('/profile/completeness', [ProfileController::class, 'completeness'])->name('member.profile.completeness');
    Route::post('/profile/photos', [ProfileController::class, 'photos'])->name('member.profile.photos');
    Route::delete('/profile/photos/{photo}', [ProfileController::class, 'destroyPhoto'])->name('member.profile.photos.destroy');
    Route::get('/matches', fn () => view('member.matches'))->name('member.matches');
    Route::get('/likes', fn () => view('member.likes'))->name('member.likes');
    Route::get('/questionnaire', [QuestionnaireController::class, 'index'])->name('member.questionnaire');
    Route::post('/questionnaire', [QuestionnaireController::class, 'store'])->name('member.questionnaire.store');
    Route::get('/who-liked', [MatchController::class, 'whoLiked'])->name('member.who-liked');
    Route::get('/visitors', [MatchController::class, 'visitors'])->name('member.visitors');
    Route::get('/favorites', fn () => view('member.favorites'))->name('member.favorites');

    Route::get('/chat', fn () => view('member.chat.inbox'))->name('member.chat');
    Route::post('/chat/create', [ChatController::class, 'create'])->name('member.chat.create');
    Route::get('/chat/conversations', [ChatController::class, 'conversations'])->name('member.chat.conversations');
    Route::get('/chat/{conversation}/export', [ChatController::class, 'export'])->name('member.chat.export');
    Route::get('/chat/{conversation}', function (Conversation $conversation) {
        abort_unless($conversation->involves(Auth::id()), 403);

        return view('member.chat.show', ['conversation' => $conversation]);
    })->name('member.chat.show');
    Route::post('/chat/{conversation}/pin', function (Conversation $conversation, ChatService $chat) {
        abort_unless($conversation->involves(Auth::id()), 403);
        $chat->setting($conversation, Auth::user(), 'is_pinned', true);

        return back();
    });
    Route::post('/chat/{conversation}/mute', function (Conversation $conversation, ChatService $chat) {
        abort_unless($conversation->involves(Auth::id()), 403);
        $chat->setting($conversation, Auth::user(), 'is_muted', true);

        return back();
    });
    Route::post('/chat/{conversation}/archive', function (Conversation $conversation, ChatService $chat) {
        abort_unless($conversation->involves(Auth::id()), 403);
        $chat->setting($conversation, Auth::user(), 'is_archived', true);

        return back();
    });
    Route::post('/chat/{conversation}/attachments', [MessageController::class, 'upload'])->name('member.chat.attachments');
    Route::get('/chat/attachments/{attachment}', [MessageController::class, 'download'])->name('member.chat.attachment.download');
    Route::get('/chat/{conversation}/labels', [ChatController::class, 'labels'])->name('member.chat.labels');
    Route::post('/chat/{conversation}/labels', [ChatController::class, 'addLabel'])->name('member.chat.labels.add');
    Route::delete('/chat/{conversation}/labels/{labelId}', [ChatController::class, 'removeLabel'])->name('member.chat.labels.remove');
    Route::post('/chat/{conversation}/mark-all-read', function (Conversation $conversation, ChatService $chat) {
        abort_unless($conversation->involves(Auth::id()), 403);
        $count = $chat->markAllRead(Auth::user());

        return back()->with('status', "{$count} pesan ditandai dibaca.");
    })->name('member.chat.mark-all-read');
    Route::post('/chat-requests/{user}', [ChatRequestController::class, 'store'])->name('member.chat-requests.store');
    Route::post('/chat/{conversation}/unmatch', function (Conversation $conversation) {
        try {
            $conversation->update(['is_blocked' => true]);
        } catch (Throwable) {
        }

        return redirect('/chat')->with('status', 'Unmatch berhasil.');
    });

    Route::get('/notifications', fn () => view('member.notifications.center'))->name('member.notifications');
    Route::post('/notifications/read-all', function (NotificationService $svc) {
        $svc->markAllRead(Auth::user());

        return back();
    });

    Route::get('/premium', fn () => view('member.premium.plans'))->name('member.premium');
    Route::post('/premium/checkout', function (Request $r, PaymentService $pay) {
        $plan = $r->input('plan', 'premium_monthly');
        try {
            $res = $pay->checkout(Auth::user(), ['items' => [['type' => 'plan', 'code' => $plan]]]);

            return redirect($res['redirect_url'] ?? '/premium')->with('status', 'Checkout dibuat.');
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    });

    Route::get('/credits', fn () => view('member.credits.wallet'))->name('member.credits');
    Route::post('/credits/checkout', function (Request $r, PaymentService $pay) {
        try {
            $res = $pay->checkout(Auth::user(), ['items' => [['type' => 'credits', 'code' => $r->input('product')]]]);

            return redirect($res['redirect_url'] ?? '/credits')->with('status', 'Checkout kredit dibuat.');
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    });

    Route::get('/verification', fn () => view('member.verification.form'))->name('member.verification');
    Route::post('/verification', function (Request $r, VerificationService $svc) {
        $data = $r->validate(['type' => 'required|string', 'notes' => 'nullable|string']);
        try {
            $svc->submit(Auth::user(), $data['type'], [], $data['notes'] ?? null);

            return back()->with('status', 'Pengajuan verifikasi terkirim. Pantau status di bawah.');
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    });

    Route::get('/settings', fn () => view('member.settings.index'))->name('member.settings');
    Route::get('/settings/login-history', [SettingsController::class, 'loginHistory'])->name('settings.login-history');
    Route::get('/settings/sessions', [SettingsController::class, 'sessions'])->name('settings.sessions');
    Route::post('/settings/profile', function (Request $r) {
        $u = Auth::user();
        $u->update($r->only(['display_name', 'city']));
        try {
            $u->profile()->updateOrCreate([], $r->only(['bio', 'occupation', 'education']));
        } catch (Throwable) {
        }

        return back()->with('status', 'Profil disimpan ✅');
    });
    Route::post('/settings/privacy', [SettingsController::class, 'privacy'])->name('settings.privacy');
    Route::post('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::post('/settings/2fa/enable', [SettingsController::class, 'enable2fa'])->name('settings.2fa.enable');
    Route::post('/settings/2fa/disable', [SettingsController::class, 'disable2fa'])->name('settings.2fa.disable');

    Route::get('/safety', [SafetyController::class, 'index'])->name('member.safety');
    Route::post('/safety/block', function (Request $r) {
        $id = (int) $r->input('user_id');
        try {
            Block::firstOrCreate(['blocker_id' => Auth::id(), 'blocked_id' => $id]);
        } catch (Throwable) {
        }

        return back()->with('status', 'User diblokir ⛔');
    });
    Route::post('/safety/unblock', function (Request $r) {
        try {
            Block::where('blocker_id', Auth::id())->where('blocked_id', (int) $r->input('user_id'))->delete();
        } catch (Throwable) {
        }

        return back()->with('status', 'Blokir dibuka.');
    });
    Route::post('/safety/report', function (Request $r) {
        $r->validate(['user_id' => 'required']);
        try {
            Report::create(['reporter_id' => Auth::id(), 'reported_user_id' => (int) $r->input('user_id'), 'reason' => $r->input('reason', 'Lainnya'), 'details' => $r->input('details'), 'status' => 'open']);
        } catch (Throwable) {
        }

        return back()->with('status', 'Laporan terkirim. Tim moderasi meninjau 🚩');
    });

    Route::get('/gifts', fn () => view('member.gifts.index'))->name('member.gifts');
    Route::post('/gifts/send', function (Request $r, GiftService $gifts) {
        $r->validate(['gift' => 'required', 'receiver_id' => 'required|integer']);
        try {
            $gifts->send(Auth::user(), User::findOrFail((int) $r->input('receiver_id')), $r->input('gift'));

            return back()->with('status', 'Gift terkirim 🎁');
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    });

    Route::get('/boosts', fn () => view('member.boosts.index'))->name('member.boosts');
    Route::post('/boosts/activate', function (BoostService $boosts) {
        try {
            $boosts->activate(Auth::user(), 30);

            return back()->with('status', 'Boost aktif 30 menit 🚀');
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    });

    Route::get('/events', fn () => view('member.events.index'))->name('member.events');
    Route::get('/events/status', function () {
        $statuses = EventStatus::cases();

        return response()->json($statuses);
    })->name('member.events.status');
    Route::get('/events/{event}', function (Event $event) {
        return view('member.events.show', ['event' => $event]);
    })->name('member.events.show');
    Route::post('/events/{event}/join', [EventController::class, 'join'])->name('member.events.join');
    Route::post('/events/{event}/leave', [EventController::class, 'leave'])->name('member.events.leave');
    Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->name('member.events.rsvp');
    Route::get('/events/{event}/attendees', [EventController::class, 'attendees'])->name('member.events.attendees');
    Route::post('/events/nearby', [EventController::class, 'nearby'])->name('member.events.nearby');

    Route::get('/biro-jodoh/taaruf', [BiroJodohController::class, 'courtships'])->name('member.biro-jodoh.courtships');
    Route::get('/biro-jodoh/taaruf/{courtship}', [BiroJodohController::class, 'showCourtship'])->name('member.biro-jodoh.courtship');
    Route::post('/biro-jodoh/taaruf/{courtship}/lanjut', [BiroJodohController::class, 'advanceCourtship'])->name('member.biro-jodoh.advance');
    Route::post('/biro-jodoh/taaruf/{courtship}/mundur', [BiroJodohController::class, 'withdrawCourtship'])->name('member.biro-jodoh.withdraw');
    Route::put('/biro-jodoh/taaruf/{courtship}/wali', [BiroJodohController::class, 'setGuardian'])->name('member.biro-jodoh.guardian');
    Route::post('/biro-jodoh/taaruf/{courtship}/wali/setuju', [BiroJodohController::class, 'approveGuardian'])->name('member.biro-jodoh.guardian.approve');
    Route::get('/biro-jodoh/konselor', [BiroJodohController::class, 'counselors'])->name('member.biro-jodoh.counselors');
    Route::get('/biro-jodoh/konsultasi', [BiroJodohController::class, 'consultations'])->name('member.biro-jodoh.consultations');
    Route::post('/biro-jodoh/konsultasi', [BiroJodohController::class, 'bookConsultation'])->name('member.biro-jodoh.consultations.book');
    Route::post('/biro-jodoh/konsultasi/{consultation}/batal', [BiroJodohController::class, 'cancelConsultation'])->name('member.biro-jodoh.consultations.cancel');
    Route::get('/biro-jodoh/laporan', [BiroJodohController::class, 'reports'])->name('member.biro-jodoh.reports');
    Route::get('/biro-jodoh/laporan/{report}', [BiroJodohController::class, 'showReport'])->name('member.biro-jodoh.report');
    Route::get('/biro-jodoh/kisah', [BiroJodohController::class, 'stories'])->name('member.biro-jodoh.stories');
    Route::get('/biro-jodoh/kisah/saya', [BiroJodohController::class, 'myStories'])->name('member.biro-jodoh.stories.mine');
    Route::post('/biro-jodoh/kisah', [BiroJodohController::class, 'submitStory'])->name('member.biro-jodoh.stories.submit');

    Route::get('/blog', fn () => view('member.blog.index'))->name('member.blog');
    Route::get('/blog/{slug}', fn (string $slug) => view('member.blog.show', ['slug' => $slug]))->name('member.blog.show');
    Route::get('/forums', fn () => view('member.forums.index'))->name('member.forums');
    Route::get('/forums/{slug}', fn (string $slug) => view('member.forums.threads', ['slug' => $slug]))->name('member.forums.threads');
    Route::get('/forums/thread/{thread}', fn (int $thread) => view('member.forums.thread', ['threadId' => $thread]))->name('member.forums.thread');
    Route::post('/forums/search', [ForumController::class, 'search'])->name('member.forums.search');
    Route::post('/forums/{slug}/threads', [ForumController::class, 'storeThread'])->name('member.forums.threads.store');
    Route::post('/forums/thread/{thread}/reply', [ForumController::class, 'reply'])->name('member.forums.thread.reply');
    Route::put('/forums/thread/{thread}', [ForumController::class, 'updateThread'])->name('member.forums.thread.update');
    Route::delete('/forums/thread/{thread}', [ForumController::class, 'destroyThread'])->name('member.forums.thread.destroy');
    Route::put('/forums/reply/{reply}', [ForumController::class, 'updateReply'])->name('member.forums.reply.update');
    Route::delete('/forums/reply/{reply}', [ForumController::class, 'destroyReply'])->name('member.forums.reply.destroy');
});

/* ---------- Admin HTML views (role-gated mirrors of admin.php) ---------- */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active.account'])->group(function () {
    Route::middleware('can:moderator')->group(function () {
        foreach (['profiles' => 'admin.profiles', 'photos' => 'admin.photos', 'verification' => 'admin.verification', 'reports' => 'admin.reports', 'blocks' => 'admin.blocks'] as $uri => $view) {
            Route::get('/'.$uri, fn () => view($view))->name(str_replace('.', '-', $uri));
        }
        Route::post('/verification/{id}/approve', function (int $id, VerificationService $svc) {
            $req = VerificationRequest::findOrFail($id);
            $svc->approve($req, Auth::user());

            return back();
        });
        Route::post('/verification/{id}/reject', function (int $id, VerificationService $svc) {
            $req = VerificationRequest::findOrFail($id);
            $svc->reject($req, Auth::user(), 'Tidak memenuhi syarat');

            return back();
        });
        foreach (['conversations' => 'admin.chat.conversations', 'messages' => 'admin.chat.messages', 'requests' => 'admin.chat.requests'] as $uri => $view) {
            Route::get('/chat/'.$uri, fn () => view($view))->name('chat-'.$uri);
        }
        foreach (['comments' => 'admin.community.comments', 'forums' => 'admin.community.forums', 'blogs' => 'admin.community.blogs'] as $uri => $view) {
            Route::get('/community/'.$uri, fn () => view($view))->name('community-'.$uri);
        }
        Route::get('/inbox', fn () => view('admin.inbox'))->name('inbox');
    });
    Route::middleware('can:admin')->group(function () {
        Route::get('/matching/options', fn () => view('admin.matching.options'))->name('matching-options');
        foreach (['plans' => 'admin.membership.plans', 'subscriptions' => 'admin.membership.subscriptions', 'credits' => 'admin.membership.credits', 'products' => 'admin.membership.products', 'payments' => 'admin.membership.payments', 'gateways' => 'admin.membership.gateways', 'coupons' => 'admin.membership.coupons'] as $uri => $view) {
            Route::get('/membership/'.$uri, fn () => view($view))->name('membership-'.$uri);
        }
        Route::get('/notifications', fn () => view('admin.notifications'))->name('notifications');
        Route::post('/notifications/send', fn (Request $r) => back()->with('status', 'Broadcast dikirim ke segmen.'));
        Route::get('/email', fn () => view('admin.email'))->name('email');
        Route::get('/audit-logs', fn () => view('admin.audit-logs'))->name('audit-logs');
    });
    Route::middleware('can:superadmin')->group(function () {
        Route::get('/feature-flags', fn () => view('admin.feature-flags'))->name('feature-flags');
    });
    Route::middleware('can:operator')->group(function () {
        Route::get('/chat/virtual', fn () => view('admin.chat.virtual'))->name('chat-virtual');
        Route::get('/chat/ai', fn () => view('admin.chat.ai'))->name('chat-ai');
        Route::get('/chat/operators', fn () => view('admin.chat.operators'))->name('chat-operators');
    });
});
