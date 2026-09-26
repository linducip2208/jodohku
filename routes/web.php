<?php

use App\Enums\EventStatus;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Member\AiAssistantController;
use App\Http\Controllers\Member\BiroJodohController;
use App\Http\Controllers\Member\ChatController;
use App\Http\Controllers\Member\ChatRequestController;
use App\Http\Controllers\Member\CommunityController;
use App\Http\Controllers\Member\ContactBlockController;
use App\Http\Controllers\Member\DatePlanController;
use App\Http\Controllers\Member\EventController;
use App\Http\Controllers\Member\FollowController;
use App\Http\Controllers\Member\ForumController;
use App\Http\Controllers\Member\GroupController;
use App\Http\Controllers\Member\HomeController;
use App\Http\Controllers\Member\LikeController;
use App\Http\Controllers\Member\MatchController;
use App\Http\Controllers\Member\MessageController;
use App\Http\Controllers\Member\OnboardingController;
use App\Http\Controllers\Member\PassportController;
use App\Http\Controllers\Member\PrivacyCenterController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\QuestionnaireController;
use App\Http\Controllers\Member\ReferralController;
use App\Http\Controllers\Member\SafetyController;
use App\Http\Controllers\Member\SavedFilterController;
use App\Http\Controllers\Member\SearchController;
use App\Http\Controllers\Member\SettingsController;
use App\Http\Controllers\Member\StoryController;
use App\Http\Controllers\PublicSeoController;
use App\Models\Block;
use App\Models\Brand;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\MembershipPlan;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Models\SuccessStory;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\VerificationRequest;
use App\Services\BoostService;
use App\Services\BrandService;
use App\Services\ChatService;
use App\Services\GiftService;
use App\Services\MatchingEngine;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\PseoService;
use App\Services\SubscriptionService;
use App\Services\TwoFactorService;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/* ---------- Landing ---------- */
Route::get('/', function () {
    $stats = ['members' => 0, 'verified' => 0, 'matches' => 0];
    $demoMembers = collect();
    $stories = collect();
    $plans = collect();
    $onlineNow = collect();
    $feedPosts = collect();
    try {
        $stats = Cache::remember('landing:stats', 3600, fn () => [
            'members' => User::active()->count(),
            'verified' => User::active()->where('is_verified', true)->count(),
            'matches' => UserMatch::where('is_active', true)->count(),
        ]);
        // Public demo showcase ONLY: demo-marked, active, real profile,
        // approved public photos. Never real members, never private photos,
        // never counselors/staff (dating context only — counselors live in
        // the consultation context, see BiroJodohController).
        $notCounselor = fn ($q) => $q->whereDoesntHave('counselor');
        // Online strip first: demo grid below skips these ids so no face
        // appears twice on one landing view (dedup by user_id, never names).
        $onlineNow = User::active()->where('is_demo', true)->where('is_online', true)->whereHas('profile')
            ->where(fn ($q) => $notCounselor($q))
            ->with(['profile'])->orderByDesc('last_active_at')->limit(10)->get();
        $onlineIds = $onlineNow->pluck('id')->all();
        $demoMembers = User::active()->where('is_demo', true)->whereHas('profile')
            ->where(fn ($q) => $notCounselor($q))
            ->when($onlineIds, fn ($q) => $q->whereNotIn('users.id', $onlineIds))
            ->with(['profile', 'interests' => fn ($q) => $q->limit(4),
                'photos' => fn ($q) => $q->where('status', 'approved')->where('is_private', false)->ordered()])
            ->inRandomOrder()->limit(24)->get()
            ->filter(fn ($u) => $u->photos->isNotEmpty())
            ->take(12)->values();
        $stories = SuccessStory::where('status', 'published')->latest('published_at')->latest('id')->limit(3)->get();
        $plans = MembershipPlan::where('is_active', true)->orderBy('sort_order')->get();
        // Product-first landing: real online demo people + public feed preview.
        // NOTE: never cache Eloquent models (repo rule — serializing drivers
        // can unserialize to __PHP_Incomplete_Class); these are 2-3 cheap
        // indexed queries. Only plain scalars stay cached (see stats above).
        // No duplicate faces across sections: demo grid skips online-strip ids.
        $feedPosts = Post::where('is_hidden', false)
            ->whereHas('user', fn ($q) => $q->active()
                ->where(fn ($qq) => $notCounselor($qq))
                ->whereDoesntHave('profilePrivacy', fn ($qq) => $qq->where('is_incognito', true)))
            ->with(['user:id,display_name,name,avatar_path,is_verified'])->withCount(['comments', 'likes'])
            // Landing preview is 1 hero post + max 3 compact cards (never a full feed).
            ->latest('id')->limit(4)->get();
    } catch (Throwable) {
    }

    return view('welcome', compact('stats', 'demoMembers', 'stories', 'plans', 'onlineNow', 'feedPosts'));
})->name('landing');

// Public brand-aware pricing (uses the active brand's plan catalog).
Route::get('/harga', fn () => view('landing.pricing'))->name('pricing');

// PWA manifest (installable, standalone, push-ready architecture).
// Brand-aware: name, theme color, and icons follow the active whitelabel brand.
Route::get('/manifest.webmanifest', function () {
    try {
        $theme = app(BrandService::class)->theme();
    } catch (Throwable) {
        $theme = ['name' => 'Jodohku', 'primary' => '#f43f5e', 'logo' => null, 'favicon' => null];
    }
    $name = (string) ($theme['name'] ?? config('app.name', 'Jodohku'));
    $icons = [
        ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => '/icons/icon-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ];
    // Per-brand generated icons win over the logo fallback and defaults.
    try {
        $slug = $theme['slug'] ?? null;
        $disk = Storage::disk('public');
        if ($slug && $disk->exists("brands/{$slug}/icons/icon-512.png")) {
            $icons = [
                ['src' => "/storage/brands/{$slug}/icons/icon-192.png", 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => "/storage/brands/{$slug}/icons/icon-512.png", 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => "/storage/brands/{$slug}/icons/icon-maskable.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];
        } elseif (! empty($theme['logo'])) {
            array_unshift($icons, ['src' => $theme['logo'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']);
        }
    } catch (Throwable) {
        if (! empty($theme['logo'])) {
            array_unshift($icons, ['src' => $theme['logo'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']);
        }
    }

    return response()->json([
        'name' => $name.' — Biro Jodoh Modern Indonesia',
        'short_name' => $name,
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#fafafb',
        'theme_color' => (string) ($theme['primary'] ?? '#f43f5e'),
        'description' => 'Social dating, matchmaking, taaruf, dan komunitas Indonesia.',
        'icons' => $icons,
    ])->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

// Brand domain-ownership file (whitelabel verification method #2).
// Looks the brand up by host directly (bypasses the verification gate,
// otherwise strict mode could never verify via HTTP).
Route::get('/.well-known/brand-verification.txt', function (Request $request) {
    try {
        $brand = Brand::where('is_active', true)->where('domain', $request->getHost())->first();
        if ($brand?->verification_token) {
            return response($brand->verification_token, 200, ['Content-Type' => 'text/plain']);
        }
    } catch (Throwable) {
    }
    abort(404);
})->name('brand.verification-file');

// PWA offline shell (cached by sw.js; never breaks web when offline).
Route::get('/offline-fallback', fn () => response()->view('pwa.offline'))->name('pwa.offline');

// Public community pages (indexable): public groups only — GroupController
// 404s anything non-public for guests (no existence leaks).
Route::get('/g/{group:slug}', [GroupController::class, 'show'])->name('public.groups.show');

/* ---------- Health (public, no secrets/internals) ---------- */
Route::get('/health', function () {
    $checks = ['database' => 'down', 'cache' => 'down', 'storage' => 'down', 'queue' => 'down'];
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Throwable) {
    }
    try {
        Cache::put('health:ping', 'ok', 30);
        $checks['cache'] = Cache::get('health:ping') === 'ok' ? 'ok' : 'down';
    } catch (Throwable) {
    }
    try {
        $checks['storage'] = is_dir(Storage::disk('public')->path('')) ? 'ok' : 'down';
    } catch (Throwable) {
    }
    try {
        $checks['queue'] = DB::table('jobs')->count() > 1000 ? 'congested' : 'ok';
    } catch (Throwable) {
    }
    $degraded = in_array('down', $checks, true);

    return response()->json([
        'status' => $degraded ? 'degraded' : 'ok',
        'version' => config('app.version', '1.0.0'),
        'checks' => $checks,
        'time' => now()->toIso8601String(),
    ], $degraded ? 503 : 200);
})->name('health');
Route::get('/privacy', fn () => view('landing.privacy'))->name('legal.privacy');
Route::get('/terms', fn () => view('landing.terms'))->name('legal.terms');
Route::get('/guidelines', fn () => view('landing.guidelines'))->name('legal.guidelines');
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

/* ---------- Public SEO/PSEO (guest-accessible, privacy-safe only) ---------- */
Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemapIndex'])->name('sitemap');
Route::get('/sitemap-{section}.xml', [PublicSeoController::class, 'sitemapSection'])
    ->whereIn('section', ['pages', 'locations', 'pseo', 'profiles'])->name('sitemap.section');
Route::get('/biro-jodoh', [PublicSeoController::class, 'hub'])->name('seo.hub');
// Constrained to the PSEO taxonomy (single-sourced from PseoService) so
// member routes (/biro-jodoh/taaruf, /kisah, /konselor, …) keep matching.
Route::get('/biro-jodoh/{city}', [PublicSeoController::class, 'city'])
    ->whereIn('city', array_keys(app(PseoService::class)->cities()))
    ->name('seo.city');
Route::get('/taaruf', [PublicSeoController::class, 'taaruf'])->name('seo.taaruf');
Route::get('/panduan/{topic}', [PublicSeoController::class, 'topic'])->name('seo.topic');
Route::get('/u/{username}', [PublicSeoController::class, 'profile'])->name('seo.profile');

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
    })->middleware('throttle:5,1,web-login')->name('login.attempt');

    Route::get('/register', fn () => view('auth.register'))->name('register');
    Route::post('/register', function (Request $r) {
        $data = $r->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|unique:users,email',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female',
            'password' => 'required|min:8|confirmed',
        ]);
        $brandId = User::currentBrandId();
        app(BrandService::class)->assertRegistrationOpen($brandId);
        $user = User::create([
            'name' => $data['name'],
            'display_name' => $data['name'],
            'email' => $data['email'],
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'password' => Hash::make($data['password']),
            'brand_id' => $brandId,
        ]);
        try {
            $user->profile()->create([]);
        } catch (Throwable) {
        }
        Auth::login($user);

        return redirect('/home');
    })->middleware('throttle:10,1,web-register')->name('register.store');

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
Route::post('/phone-verify', [PhoneVerificationController::class, 'verify'])->middleware(['auth', 'throttle:5,1,phone-verify'])->name('phone.verify.store');
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
    Route::get('/home', [HomeController::class, 'index'])->name('member.home');
    Route::get('/onboarding/{step?}', [OnboardingController::class,
        'show'])->name('member.onboarding');
    Route::post('/onboarding/{step}', [OnboardingController::class,
        'store'])->name('member.onboarding.store')->middleware('throttle:30,1,onboarding');
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
            'photos' => fn ($q) => $q->ordered()->when(! ($isSelf || ($me && $me->isStaff())), fn ($qq) => $qq->where('status', 'approved')->where('is_private', false)),
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
    Route::post('/profile/video', [ProfileController::class, 'video'])->name('member.profile.video')->middleware('throttle:5,1,profile-video');
    Route::delete('/profile/photos/{photo}', [ProfileController::class, 'destroyPhoto'])->name('member.profile.photos.destroy');
    Route::post('/profile/cover', [ProfileController::class, 'cover'])->name('member.profile.cover')->middleware('throttle:10,1,profile-cover');
    Route::delete('/profile/cover', [ProfileController::class, 'destroyCover'])->name('member.profile.cover.destroy');
    Route::get('/matches', fn () => view('member.matches'))->name('member.matches');
    Route::get('/likes', fn () => view('member.likes'))->name('member.likes');
    Route::post('/rewind', [LikeController::class,
        'rewind'])->name('member.rewind')->middleware('throttle:10,1,rewind');
    Route::post('/matches/{user}/icebreaker-send', [MatchController::class,
        'icebreakerSend'])->name('member.matches.icebreaker')->middleware('throttle:10,1,icebreaker-send');
    Route::get('/ai/icebreakers/{user}', [AiAssistantController::class,
        'icebreakers'])->name('member.ai.icebreakers')->middleware('throttle:10,1,ai-icebreakers');
    Route::post('/filter-tersimpan', [SavedFilterController::class,
        'store'])->name('member.filters.store')->middleware('throttle:20,1,saved-filters');
    Route::delete('/filter-tersimpan/{savedFilter}', [SavedFilterController::class,
        'destroy'])->name('member.filters.destroy');
    Route::patch('/filter-tersimpan/{savedFilter}', [SavedFilterController::class,
        'update'])->name('member.filters.update')->middleware('throttle:20,1,saved-filters');
    Route::post('/filter-tersimpan/{savedFilter}/duplikat', [SavedFilterController::class,
        'duplicate'])->name('member.filters.duplicate')->middleware('throttle:20,1,saved-filters');
    Route::post('/filter-tersimpan/{savedFilter}/default', [SavedFilterController::class,
        'makeDefault'])->name('member.filters.default')->middleware('throttle:20,1,saved-filters');
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
    Route::get('/chat/{conversation}/gallery', [ChatController::class, 'gallery'])->name('member.chat.gallery');
    Route::get('/chat/saved', [ChatController::class, 'saved'])->name('member.chat.saved');
    Route::get('/ai/profile-tips', [AiAssistantController::class, 'profileTips'])->name('member.ai.profile-tips');
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
    Route::post('/premium/trial', function (Request $r, SubscriptionService $subs) {
        if (! $subs->trialEligible($r->user())) {
            return back()->withErrors(['trial' => 'Trial sudah pernah dipakai.']);
        }
        try {
            $subs->startTrial($r->user());
        } catch (Throwable $e) {
            return back()->withErrors(['trial' => $e->getMessage()]);
        }

        return redirect('/premium')->with('status', 'Trial Premium aktif 🎉 Nikmati semua fitur!');
    })->name('member.premium.trial')->middleware('throttle:3,1,trial');
    Route::get('/payments', function () {
        $payments = Auth::user()->payments()->latest('id')->paginate(20);

        return view('member.payments', ['payments' => $payments]);
    })->name('member.payments');
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
        $data = $r->validate([
            'type' => 'required|string',
            'notes' => 'nullable|string|max:2000',
            'files' => 'nullable|array|max:3',
            'files.*' => 'file|max:10240|mimetypes:image/jpeg,image/png,image/webp,application/pdf,video/mp4,video/quicktime',
        ]);
        $documents = [];
        foreach ($r->file('files', []) as $file) {
            if (! $file->isValid()) {
                continue;
            }
            $documents[] = [
                'document_type' => $data['type'],
                'file_path' => $file->store('verifications', 'private'),
                'mime_type' => $file->getMimeType(),
            ];
        }
        try {
            $svc->submit(Auth::user(), $data['type'], $documents, $data['notes'] ?? null);

            return back()->with('status', 'Pengajuan verifikasi terkirim. Pantau status di bawah.');
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    })->middleware('throttle:5,1,verification-submit');

    Route::get('/settings', fn () => view('member.settings.index'))->name('member.settings');
    Route::get('/settings/login-history', [SettingsController::class, 'loginHistory'])->name('settings.login-history');
    Route::get('/settings/sessions', [SettingsController::class, 'sessions'])->name('settings.sessions');
    Route::post('/settings/profile', function (Request $r) {
        $u = Auth::user();
        $u->update($r->only(['display_name', 'city']));
        try {
            $u->profile()->updateOrCreate([], $r->only(['bio', 'occupation', 'education']));
            // Profile prompts: prompt_{index} => answer (fixed question list).
            $answers = [];
            foreach (Profile::PROMPT_QUESTIONS as $i => $q) {
                $a = trim((string) $r->input('prompt_'.$i, ''));
                if ($a !== '') {
                    $answers[$q] = mb_substr($a, 0, 300);
                }
            }
            $u->profile()->updateOrCreate([], ['prompts' => $answers ?: null]);
        } catch (Throwable) {
        }

        return back()->with('status', 'Profil disimpan ???');
    });
    Route::post('/settings/privacy', [SettingsController::class, 'privacy'])->name('settings.privacy');
    Route::post('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::post('/settings/2fa/enable', [SettingsController::class, 'enable2fa'])->name('settings.2fa.enable');
    Route::post('/settings/2fa/disable', [SettingsController::class, 'disable2fa'])->name('settings.2fa.disable');
    Route::post('/settings/2fa/totp/start', [SettingsController::class, 'startTotp'])->name('settings.2fa.totp.start')->middleware('throttle:5,1,totp-setup');
    Route::get('/settings/2fa/totp/qr', [SettingsController::class, 'totpQr'])->name('settings.2fa.totp.qr')->middleware('throttle:10,1,totp-qr');
    Route::post('/settings/2fa/totp/confirm', [SettingsController::class, 'confirmTotp'])->name('settings.2fa.totp.confirm')->middleware('throttle:10,1,totp-confirm');
    Route::post('/settings/2fa/totp/backup', [SettingsController::class, 'regenerateBackupCodes'])->name('settings.2fa.totp.backup')->middleware('throttle:5,1,totp-backup');
    Route::delete('/settings/account', [SettingsController::class, 'destroy'])->name('settings.account.destroy')->middleware('throttle:5,1,account-delete');

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
        $reasonMap = ['Spam/scam' => 'scam', 'Foto palsu' => 'fake_profile', 'Pelecehan' => 'harassment', 'Lainnya' => 'other'];
        $reason = $reasonMap[$r->input('reason')] ?? 'other';
        try {
            Report::create(['reporter_id' => Auth::id(), 'reported_user_id' => (int) $r->input('user_id'), 'reason' => $reason, 'details' => $r->input('details'), 'status' => 'pending']);
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

    // Events (brand-gated: nonaktif → 404).
    Route::middleware('brand.feature:events')->group(function () {
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
        Route::get('/events/{event}/kenalan', [EventController::class, 'suggested'])->name('member.events.suggested');
        Route::post('/events/{event}/speed/start', [EventController::class, 'startSpeedRounds'])->name('member.events.speed.start')->middleware('throttle:5,1,events');
        Route::get('/events/{event}/speed', [EventController::class, 'speedRounds'])->name('member.events.speed');
        Route::post('/events/{event}/discussion', [EventController::class, 'discussion'])->name('member.events.discussion')->middleware('throttle:5,1,events');
        Route::post('/events/nearby', [EventController::class, 'nearby'])->name('member.events.nearby');
        Route::post('/events', [EventController::class,
            'store'])->name('member.events.store')->middleware('throttle:5,1,event-create');
    });

    // Ajak Kencan: propose → accept/decline → H-24 reminder.
    Route::get('/dates', [DatePlanController::class, 'index'])->name('member.dates');
    Route::post('/dates', [DatePlanController::class, 'store'])->name('member.dates.store')->middleware('throttle:10,1,dates');
    Route::post('/dates/{datePlan}/respond', [DatePlanController::class, 'respond'])->name('member.dates.respond')->middleware('throttle:20,1,dates');
    Route::delete('/dates/{datePlan}', [DatePlanController::class, 'destroy'])->name('member.dates.destroy');

    // Biro jodoh (brand-gated: taaruf/counselor nonaktif → 404).
    Route::middleware('brand.feature:taaruf')->group(function () {
        Route::get('/biro-jodoh/taaruf', [BiroJodohController::class, 'courtships'])->name('member.biro-jodoh.courtships');
        Route::post('/biro-jodoh/taaruf/mulai', [BiroJodohController::class, 'startCourtship'])->name('member.biro-jodoh.start');
        Route::get('/biro-jodoh/taaruf/{courtship}', [BiroJodohController::class, 'showCourtship'])->name('member.biro-jodoh.courtship');
        Route::post('/biro-jodoh/taaruf/{courtship}/lanjut', [BiroJodohController::class, 'advanceCourtship'])->name('member.biro-jodoh.advance');
        Route::post('/biro-jodoh/taaruf/{courtship}/mundur', [BiroJodohController::class, 'withdrawCourtship'])->name('member.biro-jodoh.withdraw');
        Route::put('/biro-jodoh/taaruf/{courtship}/wali', [BiroJodohController::class, 'setGuardian'])->name('member.biro-jodoh.guardian');
        Route::post('/biro-jodoh/taaruf/{courtship}/wali/setuju', [BiroJodohController::class, 'approveGuardian'])->name('member.biro-jodoh.guardian.approve');
        Route::get('/biro-jodoh/taaruf/{courtship}/ringkasan', [BiroJodohController::class, 'digest'])->name('member.biro-jodoh.digest');
        Route::middleware('brand.feature:counselor')->group(function () {
            Route::get('/biro-jodoh/konselor', [BiroJodohController::class, 'counselors'])->name('member.biro-jodoh.counselors');
            Route::get('/biro-jodoh/konsultasi', [BiroJodohController::class, 'consultations'])->name('member.biro-jodoh.consultations');
            Route::post('/biro-jodoh/konsultasi', [BiroJodohController::class, 'bookConsultation'])->name('member.biro-jodoh.consultations.book');
            Route::post('/biro-jodoh/konsultasi/{consultation}/batal', [BiroJodohController::class, 'cancelConsultation'])->name('member.biro-jodoh.consultations.cancel');
            Route::get('/biro-jodoh/laporan', [BiroJodohController::class, 'reports'])->name('member.biro-jodoh.reports');
            Route::get('/biro-jodoh/laporan/{report}', [BiroJodohController::class, 'showReport'])->name('member.biro-jodoh.report');
        });
        Route::get('/biro-jodoh/kisah', [BiroJodohController::class, 'stories'])->name('member.biro-jodoh.stories');
        Route::get('/biro-jodoh/kisah/saya', [BiroJodohController::class, 'myStories'])->name('member.biro-jodoh.stories.mine');
        Route::post('/biro-jodoh/kisah', [BiroJodohController::class, 'submitStory'])->name('member.biro-jodoh.stories.submit');
    });

    // Blog + komunitas (brand-gated: nonaktif → 404).
    Route::middleware('brand.feature:community')->group(function () {
        Route::get('/blog', fn () => view('member.blog.index'))->name('member.blog');
        Route::get('/blog/{slug}', fn (string $slug) => view('member.blog.show', ['slug' => $slug]))->name('member.blog.show');
        Route::get('/komunitas', [CommunityController::class, 'index'])->name('member.community');
        Route::post('/komunitas', [CommunityController::class, 'store'])->name('member.community.store')->middleware('throttle:10,1,community-post');
        Route::post('/komunitas/{post}/like', [CommunityController::class, 'toggleLike'])->name('member.community.like');
        Route::post('/komunitas/{post}/komentar', [CommunityController::class, 'comment'])->name('member.community.comment')->middleware('throttle:30,1,community-comment');
        Route::delete('/komunitas/{post}', [CommunityController::class, 'destroy'])->name('member.community.destroy');
        Route::post('/komunitas/postingan/{post}/laporkan', [CommunityController::class,
            'report'])->name('member.community.report')->middleware('throttle:10,1,community-report');
        Route::post('/komunitas/{post}/reaksi', [CommunityController::class,
            'react'])->name('member.community.react')->middleware('throttle:60,1,community-react');
        Route::post('/komunitas/{post}/simpan', [CommunityController::class,
            'bookmark'])->name('member.community.bookmark')->middleware('throttle:60,1,community-bookmark');
        Route::get('/komunitas/tersimpan', [CommunityController::class, 'bookmarks'])->name('member.community.bookmarks');
        Route::post('/komunitas/{post}/bagikan', [CommunityController::class,
            'share'])->name('member.community.share')->middleware('throttle:20,1,community-share');
        Route::put('/komunitas/{post}', [CommunityController::class, 'postUpdate'])->name('member.community.update');
        Route::post('/komunitas/{post}/boost', [CommunityController::class,
            'boostPost'])->name('member.community.boost')->middleware('throttle:5,1,community-boost');
        Route::put('/komunitas/komentar/{comment}', [CommunityController::class, 'commentUpdate'])->name('member.community.comment.update');
        Route::delete('/komunitas/komentar/{comment}', [CommunityController::class, 'commentDestroy'])->name('member.community.comment.destroy');
        Route::post('/komunitas/komentar/{comment}/laporkan', [CommunityController::class,
            'reportComment'])->name('member.community.comment.report')->middleware('throttle:10,1,community-report');
        Route::post('/komunitas/komentar/{comment}/reaksi', [CommunityController::class,
            'commentReact'])->name('member.community.comment.react')->middleware('throttle:60,1,community-react');
    });

    // Social graph: follow / mute / suggested.
    Route::get('/pengikut/{user}', [FollowController::class, 'followers'])->name('member.followers');
    Route::get('/mengikuti/{user}', [FollowController::class, 'following'])->name('member.following');
    Route::get('/suggested', [FollowController::class, 'suggested'])->name('member.suggested');
    // Passport / travel mode (premium entitlement enforced in service).
    Route::get('/passport', [PassportController::class, 'show'])->name('member.passport');
    Route::post('/passport', [PassportController::class,
        'store'])->name('member.passport.store')->middleware('throttle:10,1,passport');
    Route::delete('/passport', [PassportController::class, 'destroy'])->name('member.passport.destroy');
    // Privacy-preserving contact blocking (hashes only, never raw numbers).
    Route::get('/kontak-blokir', [ContactBlockController::class, 'show'])->name('member.contacts');
    Route::post('/kontak-blokir', [ContactBlockController::class,
        'store'])->name('member.contacts.store')->middleware('throttle:5,1,contact-import');
    Route::delete('/kontak-blokir', [ContactBlockController::class, 'destroy'])->name('member.contacts.destroy');
    // Referral dashboard + affiliate application.
    Route::get('/referral', [ReferralController::class, 'dashboard'])->name('member.referral');
    Route::post('/referral/affiliate', [ReferralController::class,
        'applyAffiliate'])->name('member.referral.affiliate')->middleware('throttle:5,1,affiliate-apply');
    // Privacy Center: blocks/mutes/sessions/export/pause in one place.
    Route::get('/privasi', [PrivacyCenterController::class, 'index'])->name('member.privacy');
    Route::delete('/privasi/sesi/{id}', [PrivacyCenterController::class,
        'revokeSession'])->name('member.privacy.session');
    Route::post('/privasi/jeda', [PrivacyCenterController::class, 'pause'])->name('member.privacy.pause');
    Route::post('/privasi/lanjut', [PrivacyCenterController::class, 'resume'])->name('member.privacy.resume');
    Route::get('/privasi/ekspor', [PrivacyCenterController::class, 'export'])->name('member.privacy.export');
    Route::post('/ikuti/{user}', [FollowController::class,
        'store'])->name('member.follow')->middleware('throttle:30,1,social-follow');
    Route::delete('/ikuti/{user}', [FollowController::class, 'destroy'])->name('member.unfollow');
    Route::post('/bisukan/{user}', [FollowController::class,
        'mute'])->name('member.mute')->middleware('throttle:30,1,social-mute');
    Route::delete('/bisukan/{user}', [FollowController::class, 'unmute'])->name('member.unmute');

    // Stories (24h, scope-based expiry).
    Route::get('/stories', [StoryController::class, 'index'])->name('member.stories');
    Route::post('/stories', [StoryController::class,
        'store'])->name('member.stories.store')->middleware('throttle:10,1,story-create');
    Route::get('/stories/{story}', [StoryController::class, 'show'])->name('member.stories.show');
    Route::post('/stories/{story}/reaksi', [StoryController::class,
        'react'])->name('member.stories.react')->middleware('throttle:60,1,story-react');
    Route::delete('/stories/{story}', [StoryController::class, 'destroy'])->name('member.stories.destroy');
    Route::post('/stories/{story}/laporkan', [StoryController::class,
        'report'])->name('member.stories.report')->middleware('throttle:10,1,story-report');

    // Communities (Groups, brand-gated).
    Route::middleware('brand.feature:community')->group(function () {
        Route::get('/groups', [GroupController::class, 'index'])->name('member.groups');
        Route::post('/groups', [GroupController::class,
            'store'])->name('member.groups.store')->middleware('throttle:5,1,group-create');
        Route::get('/groups/{group:slug}', [GroupController::class, 'show'])->name('member.groups.show');
        Route::post('/groups/{group}/join', [GroupController::class,
            'join'])->name('member.groups.join')->middleware('throttle:20,1,group-join');
        Route::delete('/groups/{group}/leave', [GroupController::class, 'leave'])->name('member.groups.leave');
        // Premium platform: group cover / invite / join-request + member event create.
        Route::post('/groups/{group}/cover', [GroupController::class,
            'cover'])->name('member.groups.cover')->middleware('throttle:10,1,group-cover');
        Route::post('/groups/{group}/invite', [GroupController::class,
            'invite'])->name('member.groups.invite')->middleware('throttle:30,1,group-invite');
        Route::post('/groups/{group}/request', [GroupController::class,
            'requestJoin'])->name('member.groups.request')->middleware('throttle:10,1,group-request');
        Route::post('/groups/{group}/requests/{joinRequest}', [GroupController::class,
            'decideRequest'])->name('member.groups.requests.decide')->middleware('throttle:30,1,group-manage');
        Route::put('/groups/{group}', [GroupController::class, 'update'])->name('member.groups.update');
        Route::post('/groups/{group}/members', [GroupController::class,
            'manageMember'])->name('member.groups.members')->middleware('throttle:30,1,group-manage');
    });

    // Global search.
    Route::get('/cari', [SearchController::class, 'index'])->name('member.search');
    // Forums (brand-gated community).
    Route::middleware('brand.feature:community')->group(function () {
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
