<?php

use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BoostAdminController;
use App\Http\Controllers\Admin\ChatAdminController;
use App\Http\Controllers\Admin\CommunityAdminController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CreditAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FraudController;
use App\Http\Controllers\Admin\GatewayController;
use App\Http\Controllers\Admin\GiftAdminController;
use App\Http\Controllers\Admin\MatchingAdminController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\NotificationAdminController;
use App\Http\Controllers\Admin\OperatorController;
use App\Http\Controllers\Admin\PaymentAdminController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionAdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationAdminController;
use App\Http\Controllers\Admin\VirtualMemberAdminController;
use Illuminate\Support\Facades\Route;

// Role granularity: moderator handles trust & safety content but NEVER money,
// gateway secrets, or system settings. Operator handles live chat/virtual only.
// Admins keep full access via the moderator/operator gates; superadmin owns secrets.
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth', 'active.account'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('can:staff')->name('dashboard');

    // ---- Trust & safety: moderator+ ----
    Route::middleware('can:moderator')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
        Route::get('/photos/queue', [UserController::class, 'photoQueue'])->name('photos.queue');
        Route::post('/photos/{photo}/moderate', [UserController::class, 'moderatePhoto'])->name('photos.moderate');

        Route::get('/moderation', [ModerationController::class, 'queues'])->name('moderation.queues');
        Route::post('/moderation/{queue}/decide', [ModerationController::class, 'decide'])->name('moderation.decide');
        Route::get('/moderation/words', [ModerationController::class, 'words'])->name('moderation.words');
        Route::post('/moderation/words', [ModerationController::class, 'storeWord'])->name('moderation.words.store');
        Route::put('/moderation/words/{word}', [ModerationController::class, 'updateWord'])->name('moderation.words.update');
        Route::delete('/moderation/words/{word}', [ModerationController::class, 'destroyWord'])->name('moderation.words.destroy');
        Route::post('/reports/{report}/resolve', [ModerationController::class, 'resolveReport'])->name('reports.resolve');

        Route::get('/verifications', [VerificationAdminController::class, 'index'])->name('verifications');
        Route::post('/verifications/{verification}/approve', [VerificationAdminController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification}/reject', [VerificationAdminController::class, 'reject'])->name('verifications.reject');

        Route::get('/fraud', [FraudController::class, 'index'])->name('fraud');
        Route::get('/fraud/users/{user}/score', [FraudController::class, 'score'])->name('fraud.score');
        Route::post('/fraud/{event}/resolve', [FraudController::class, 'resolve'])->name('fraud.resolve');

        Route::get('/chat/active', [ChatAdminController::class, 'active'])->name('chat.active');
        Route::get('/chat/reported', [ChatAdminController::class, 'reported'])->name('chat.reported');
        Route::get('/chat/flagged', [ChatAdminController::class, 'flagged'])->name('chat.flagged');
        Route::get('/chat/search', [ChatAdminController::class, 'search'])->name('chat.search');

        Route::get('/community/posts', [CommunityAdminController::class, 'posts'])->name('community.posts');
        Route::post('/community/posts/{post}/moderate', [CommunityAdminController::class, 'moderatePost'])->name('community.posts.moderate');
        Route::match(['get', 'post'], '/community/groups', [CommunityAdminController::class, 'groups'])->name('community.groups');
        Route::match(['get', 'post'], '/community/events', [CommunityAdminController::class, 'events'])->name('community.events');
        Route::put('/community/events/{event}', [CommunityAdminController::class, 'updateEvent'])->name('community.events.update');
        Route::match(['get', 'post'], '/community/blogs', [CommunityAdminController::class, 'blogs'])->name('community.blogs');
        Route::put('/community/blogs/{blog}', [CommunityAdminController::class, 'updateBlog'])->name('community.blogs.update');
        Route::delete('/community/blogs/{blog}', [CommunityAdminController::class, 'destroyBlog'])->name('community.blogs.destroy');
        Route::match(['get', 'post'], '/community/forums', [CommunityAdminController::class, 'forums'])->name('community.forums');
        Route::post('/community/threads/{thread}/moderate', [CommunityAdminController::class, 'moderateThread'])->name('community.threads.moderate');
        Route::post('/community/replies/{reply}/moderate', [CommunityAdminController::class, 'moderateReply'])->name('community.replies.moderate');

        Route::get('/inbox', [NotificationAdminController::class, 'inbox'])->name('inbox');
        Route::post('/inbox/{message}', [NotificationAdminController::class, 'handle'])->name('inbox.handle');
    });

    // ---- Live chat & virtual members: operator+ ----
    Route::middleware('can:operator')->group(function () {
        Route::get('/chat/ai', [ChatAdminController::class, 'virtual'])->name('chat.ai');
        Route::get('/virtual/dashboard', [VirtualMemberAdminController::class, 'dashboard'])->name('virtual.dashboard');
        Route::get('/virtual/triggers', [VirtualMemberAdminController::class, 'triggers'])->name('virtual.triggers');
        Route::get('/virtual/schedules', [VirtualMemberAdminController::class, 'schedules'])->name('virtual.schedules');

        Route::get('/operators/queue', [OperatorController::class, 'queue'])->name('operators.queue');
        Route::get('/operators/assignments', [OperatorController::class, 'assignments'])->name('operators.assignments');
        Route::post('/operators/vc/{vc}/takeover', [OperatorController::class, 'takeover'])->name('operators.takeover');
        Route::post('/operators/vc/{vc}/pause', [OperatorController::class, 'pause'])->name('operators.pause');
        Route::post('/operators/vc/{vc}/resume', [OperatorController::class, 'resume'])->name('operators.resume');
        Route::post('/operators/vc/{vc}/close', [OperatorController::class, 'close'])->name('operators.close');
        Route::post('/operators/conversations/{conversation}/assign', [OperatorController::class, 'assign'])->name('operators.assign');
    });

    // ---- Product & money configuration: admin+ ----
    Route::middleware('can:admin')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
        Route::post('/users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/credits', [UserController::class, 'adjustCredits'])->name('users.credits');
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
        Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/users/stop-impersonate', [UserController::class, 'stopImpersonate'])->name('users.stop-impersonate');
        Route::post('/users/bulk-action', [UserController::class, 'bulkAction'])->name('users.bulk-action');

        Route::get('/matching/questions', [MatchingAdminController::class, 'questions'])->name('matching.questions');
        Route::post('/matching/questions', [MatchingAdminController::class, 'storeQuestion'])->name('matching.questions.store');
        Route::put('/matching/questions/{question}', [MatchingAdminController::class, 'updateQuestion'])->name('matching.questions.update');
        Route::delete('/matching/questions/{question}', [MatchingAdminController::class, 'destroyQuestion'])->name('matching.questions.destroy');
        Route::match(['get', 'post'], '/matching/categories', [MatchingAdminController::class, 'categories'])->name('matching.categories');
        Route::match(['get', 'post'], '/matching/versions', [MatchingAdminController::class, 'versions'])->name('matching.versions');
        Route::match(['get', 'post', 'put'], '/matching/weights', [MatchingAdminController::class, 'weights'])->name('matching.weights');
        Route::get('/matching/demographic', [MatchingAdminController::class, 'demographic'])->name('matching.demographic');
        Route::post('/matching/weights/sync', [MatchingAdminController::class, 'syncWeights'])->name('matching.weights.sync');

        Route::get('/virtual', [VirtualMemberAdminController::class, 'index'])->name('virtual.index');
        Route::post('/virtual', [VirtualMemberAdminController::class, 'store'])->name('virtual.store');
        Route::put('/virtual/{user}', [VirtualMemberAdminController::class, 'update'])->name('virtual.update');
        Route::delete('/virtual/{user}', [VirtualMemberAdminController::class, 'destroy'])->name('virtual.destroy');
        Route::match(['get', 'post'], '/virtual/personalities', [VirtualMemberAdminController::class, 'personalities'])->name('virtual.personalities');
        Route::match(['get', 'post'], '/virtual/templates', [VirtualMemberAdminController::class, 'templates'])->name('virtual.templates');
        Route::post('/virtual/triggers', [VirtualMemberAdminController::class, 'storeTrigger'])->name('virtual.triggers.store');

        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('/subscriptions', [SubscriptionAdminController::class, 'index'])->name('subscriptions');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionAdminController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/extend', [SubscriptionAdminController::class, 'extend'])->name('subscriptions.extend');

        Route::get('/payments', [PaymentAdminController::class, 'index'])->name('payments');
        Route::get('/payments/{payment}', [PaymentAdminController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/refund', [PaymentAdminController::class, 'refund'])->name('payments.refund');

        Route::get('/gateways', [GatewayController::class, 'index'])->name('gateways');
        Route::post('/gateways/{gateway}/toggle', [GatewayController::class, 'toggle'])->name('gateways.toggle');
        Route::post('/gateways/{gateway}/priority', [GatewayController::class, 'priority'])->name('gateways.priority');

        Route::match(['get', 'post'], '/credits/products', [CreditAdminController::class, 'products'])->name('credits.products');
        Route::put('/credits/products/{product}', [CreditAdminController::class, 'updateProduct'])->name('credits.products.update');
        Route::get('/credits/transactions', [CreditAdminController::class, 'transactions'])->name('credits.transactions');

        Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::post('/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
        Route::get('/coupons/redemptions', [CouponController::class, 'redemptions'])->name('coupons.redemptions');

        Route::get('/gifts', [GiftAdminController::class, 'index'])->name('gifts');
        Route::post('/gifts', [GiftAdminController::class, 'store'])->name('gifts.store');
        Route::put('/gifts/{gift}', [GiftAdminController::class, 'update'])->name('gifts.update');
        Route::delete('/gifts/{gift}', [GiftAdminController::class, 'destroy'])->name('gifts.destroy');
        Route::get('/gifts/transactions', [GiftAdminController::class, 'transactions'])->name('gifts.transactions');

        Route::get('/boosts', [BoostAdminController::class, 'index'])->name('boosts');
        Route::match(['get', 'post', 'put'], '/boosts/pricing', [BoostAdminController::class, 'pricing'])->name('boosts.pricing');

        Route::get('/ads', [AdController::class, 'index'])->name('ads');
        Route::post('/ads', [AdController::class, 'store'])->name('ads.store');
        Route::put('/ads/{ad}', [AdController::class, 'update'])->name('ads.update');
        Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');

        Route::post('/notifications/broadcast', [NotificationAdminController::class, 'broadcast'])->name('notifications.broadcast');

        Route::get('/analytics', [AnalyticsController::class, 'overview'])->name('analytics');
        Route::get('/analytics/funnel', [AnalyticsController::class, 'funnel'])->name('analytics.funnel');
        Route::get('/analytics/kpi', [AnalyticsController::class, 'kpi'])->name('analytics.kpi');
        Route::get('/analytics/gateways', [AnalyticsController::class, 'gateways'])->name('analytics.gateways');
        Route::get('/analytics/top-users', [AnalyticsController::class, 'topUsers'])->name('analytics.top-users');
        Route::get('/analytics/engagement', [AnalyticsController::class, 'engagement'])->name('analytics.engagement');
        Route::get('/analytics/cohorts', [AnalyticsController::class, 'cohorts'])->name('analytics.cohorts');
        Route::get('/ai-usage', [AiUsageController::class, 'index'])->name('ai-usage');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit');
    });

    // ---- Secrets & system switches: superadmin only ----
    Route::middleware('can:superadmin')->group(function () {
        Route::post('/gateways/{gateway}/credentials', [GatewayController::class, 'credentials'])->name('gateways.credentials');
        Route::post('/gateways/{gateway}/test', [GatewayController::class, 'test'])->name('gateways.test');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::match(['get', 'post', 'put'], '/settings/flags', [SettingsController::class, 'flags'])->name('settings.flags');
    });
});
