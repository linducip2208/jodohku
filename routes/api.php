<?php

use App\Http\Controllers\Admin\GatewayController as AdminGatewayController;
use App\Http\Controllers\Admin\ModerationController as AdminModerationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DiscoveryController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushController;
use App\Http\Controllers\Api\V1\SavedFilterController;
use App\Http\Controllers\Api\V1\SocialController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Member\AiAssistantController;
use App\Http\Controllers\Member\BiroJodohController;
use App\Http\Controllers\Member\BlogController;
use App\Http\Controllers\Member\ChatRequestController;
use App\Http\Controllers\Member\EventController;
use App\Http\Controllers\Member\ForumController;
use App\Http\Controllers\Member\MatchController;
use App\Http\Controllers\Member\MessageController as MemberMessageController;
use App\Http\Controllers\Member\SafetyController;
use App\Http\Controllers\Member\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public health alias (single source of truth lives at GET /health).
    Route::get('/health', fn () => redirect('/health'))->middleware('throttle:60,1,api-health')->name('health');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1,auth-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1,auth-login');

    // Auth namespace aliases (used by clients + tests).
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1,auth-register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1,auth-login');
        Route::post('/2fa/verify', [AuthController::class, 'verify2fa'])->middleware('throttle:10,1,auth-2fa');
        Route::middleware(['auth:sanctum', 'active.account'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/2fa/enable', [SettingsController::class, 'enable2fa']);
            Route::post('/2fa/disable', [SettingsController::class, 'disable2fa']);
            Route::post('/2fa/totp/start', [SettingsController::class, 'startTotp'])->middleware('throttle:5,1,totp-setup');
            Route::post('/2fa/totp/confirm', [SettingsController::class, 'confirmTotp'])->middleware('throttle:10,1,totp-confirm');
            Route::post('/2fa/totp/backup', [SettingsController::class, 'regenerateBackupCodes'])->middleware('throttle:5,1,totp-backup');
            Route::delete('/account', [SettingsController::class, 'destroy'])->middleware('throttle:5,1,account-delete');
        });
    });

    // Payment gateway webhooks are public; the gateway HMAC signature is the
    // source of truth (never browser redirects). Idempotent via event_id.
    Route::post('/webhooks/{gateway}', WebhookController::class)
        ->middleware('throttle:60,1,webhooks')
        ->name('webhooks.handle');

    Route::middleware(['auth:sanctum', 'active.account', 'throttle:120,1,api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/profile', [ProfileController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photos', [App\Http\Controllers\Member\ProfileController::class, 'photos']);
        Route::post('/profile/video', [App\Http\Controllers\Member\ProfileController::class, 'video'])->middleware('throttle:5,1,profile-video');
        Route::delete('/profile/photos/{photo}', [App\Http\Controllers\Member\ProfileController::class, 'destroyPhoto']);
        Route::get('/profile/{user}', [ProfileController::class, 'show'])->middleware('blocked');
        Route::get('/profile/{user}/view-history', [ProfileController::class, 'viewHistory']);
        Route::get('/profile/{user}/viewers', [ProfileController::class, 'viewers']);
        Route::get('/profile/{user}/visible-to', [ProfileController::class, 'visibleTo']);
        Route::get('/profile/completeness', [ProfileController::class, 'completeness']);

        Route::get('/preferences', [PreferenceController::class, 'show']);
        Route::put('/preferences', [PreferenceController::class, 'update']);
        Route::get('/questions', [PreferenceController::class, 'questions']);
        Route::get('/questionnaire/answers', [PreferenceController::class, 'answers']);
        Route::post('/questionnaire/answers', [PreferenceController::class, 'answer']);

        // Saved discovery filters (Flutter-ready CRUD).
        Route::get('/saved-filters', [SavedFilterController::class, 'index']);
        Route::post('/saved-filters', [SavedFilterController::class, 'store'])->middleware('throttle:20,1,saved-filters');
        Route::patch('/saved-filters/{savedFilter}', [SavedFilterController::class, 'update'])->middleware('throttle:20,1,saved-filters');
        Route::post('/saved-filters/{savedFilter}/duplicate', [SavedFilterController::class, 'duplicate'])->middleware('throttle:20,1,saved-filters');
        Route::post('/saved-filters/{savedFilter}/default', [SavedFilterController::class, 'makeDefault'])->middleware('throttle:20,1,saved-filters');
        Route::delete('/saved-filters/{savedFilter}', [SavedFilterController::class, 'destroy']);

        Route::get('/discover', [DiscoveryController::class, 'discover']);
        // Social graph + feed + stories + search (SocialController).
        Route::post('/social/follow/{user}', [SocialController::class, 'follow'])->middleware('throttle:30,1,social-follow');
        Route::delete('/social/follow/{user}', [SocialController::class, 'unfollow']);
        Route::get('/social/followers/{user}', [SocialController::class, 'followers']);
        Route::get('/social/following/{user}', [SocialController::class, 'following']);
        Route::get('/social/suggested', [SocialController::class, 'suggested']);
        Route::get('/social/feed', [SocialController::class, 'feed']);
        Route::post('/social/posts/{post}/react', [SocialController::class, 'reactPost'])->middleware('throttle:60,1,social-react');
        Route::post('/social/comments/{comment}/react', [SocialController::class, 'reactComment'])->middleware('throttle:60,1,social-react');
        Route::get('/social/stories', [SocialController::class, 'stories']);
        Route::get('/social/stories/{story}', [SocialController::class, 'showStory']);
        Route::get('/social/search', [SocialController::class, 'search']);
        Route::get('/social/groups', [SocialController::class, 'groups'])->middleware('brand.feature:community');
        Route::get('/social/recommendations', [SocialController::class, 'recommendations']);
        Route::get('/discover/picks', [DiscoveryController::class, 'picks']);
        Route::post('/discover/picks/reset', [DiscoveryController::class, 'resetPicks']);
        Route::get('/matches', [DiscoveryController::class, 'matches']);
        Route::get('/matches/{user}/explain', [DiscoveryController::class, 'explain']);
        Route::get('/matches/{user}/score-cache', [DiscoveryController::class, 'scoreCache']);
        Route::post('/matches/batch-score', [DiscoveryController::class, 'batchScore']);
        Route::get('/matches/stats', [DiscoveryController::class, 'stats']);
        Route::get('/matches/history', [DiscoveryController::class, 'history']);
        Route::delete('/matches/{user}', [MatchController::class, 'destroy']);
        Route::get('/matches/{user}/note', [MatchController::class, 'showNote']);
        Route::put('/matches/{user}/note', [MatchController::class, 'storeNote']);
        Route::get('/who-liked', [MatchController::class, 'whoLiked']);
        Route::get('/visitors', [MatchController::class, 'visitors']);
        Route::post('/likes/{user}', [DiscoveryController::class, 'like']);
        Route::get('/likes/quota', [DiscoveryController::class, 'likeQuota']);
        Route::delete('/likes/{user}', [DiscoveryController::class, 'unlike']);
        Route::post('/passes/{user}', [DiscoveryController::class, 'pass']);
        // Backward compat: old clients used DELETE /likes as pass.
        Route::post('/likes/{user}/pass', [DiscoveryController::class, 'pass']);
        Route::post('/superlikes/{user}', [DiscoveryController::class, 'superlike']);
        Route::post('/favorites/{user}', [DiscoveryController::class, 'favorite']);
        Route::delete('/favorites/{user}', [DiscoveryController::class, 'unfavorite']);
        Route::post('/rewind', [DiscoveryController::class, 'rewind']);

        Route::get('/conversations', [ChatController::class, 'conversations']);
        Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:30,1,chat-send');
        Route::post('/chat/{conversation}/attachments', [ChatController::class,
            'upload'])->middleware('throttle:30,1,chat-upload');
        Route::get('/chat/attachments/{attachment}', [MemberMessageController::class, 'download']);
        Route::post('/conversations/{conversation}/attachments', [ChatController::class, 'upload'])->middleware('throttle:30,1,chat-upload');
        Route::get('/chat/conversations', [ChatController::class, 'conversations']);
        Route::get('/chat/conversations/search', [ChatController::class, 'search']);
        Route::get('/chat/overview', [ChatController::class, 'overview']);
        Route::get('/chat/quota', [ChatController::class, 'quota']);
        Route::get('/chat/themes', [ChatController::class, 'themes']);
        Route::get('/chat/stickers', [ChatController::class, 'stickers']);
        Route::get('/conversations/{conversation}/catch-up', [ChatController::class, 'catchUp']);
        Route::get('/chat/{conversation}/safety', [ChatController::class, 'safetyHint']);
        Route::patch('/conversations/{conversation}/disappearing', [ChatController::class, 'disappearing']);
        Route::post('/messages/{message}/poll/vote', [ChatController::class, 'votePoll']);
        Route::get('/messages/{message}/poll', [ChatController::class, 'pollResults']);
        Route::post('/messages/{message}/translate', [ChatController::class, 'translate'])->middleware('throttle:20,1,ai-translate');
        Route::get('/chat/{conversation}/stats', [ChatController::class, 'stats']);
        Route::post('/chat/{conversation}/clear-history', [ChatController::class, 'clearHistory']);
        Route::get('/chat/{conversation}/labels', [ChatController::class, 'labels']);
        Route::post('/chat/{conversation}/labels', [ChatController::class, 'addLabel']);
        Route::delete('/chat/{conversation}/labels/{labelId}', [ChatController::class, 'removeLabel']);
        Route::post('/chat/{conversation}/mark-all-read', [ChatController::class, 'markAllRead']);
        Route::post('/conversations/{conversation}/read', [ChatController::class, 'read']);
        Route::post('/conversations/{conversation}/typing', [ChatController::class, 'typing']);
        Route::get('/calls/rates', [CallController::class, 'rates']);
        Route::get('/calls/ice', [CallController::class, 'ice']);
        Route::post('/conversations/{conversation}/calls', [CallController::class, 'invite'])->middleware('throttle:10,1,calls');
        Route::get('/conversations/{conversation}/calls', [CallController::class, 'history']);
        Route::post('/calls/{call}/accept', [CallController::class, 'accept']);
        Route::post('/calls/{call}/reject', [CallController::class, 'reject']);
        Route::post('/calls/{call}/cancel', [CallController::class, 'cancel']);
        Route::post('/calls/{call}/end', [CallController::class, 'end']);
        Route::post('/conversations/{conversation}/scheduled-messages', [ChatController::class, 'schedule'])->middleware('throttle:20,1,chat-schedule');
        Route::get('/conversations/{conversation}/scheduled-messages', [ChatController::class, 'scheduled']);
        Route::delete('/scheduled-messages/{scheduled}', [ChatController::class, 'cancelScheduled']);
        Route::patch('/conversations/{conversation}/settings', [ChatController::class, 'setting']);
        Route::get('/conversations/{conversation}/search', [ChatController::class, 'searchInConversation']);
        Route::get('/conversations/{conversation}/gallery', [ChatController::class, 'gallery']);
        Route::get('/conversations/{conversation}/export', [ChatController::class, 'export']);
        Route::patch('/messages/{message}', [ChatController::class, 'edit']);
        Route::delete('/messages/{message}', [ChatController::class, 'delete']);
        Route::get('/messages/{message}/reactions', [ChatController::class, 'reactions']);
        Route::post('/messages/{message}/reactions', [ChatController::class, 'react']);
        Route::delete('/messages/{message}/reactions', [ChatController::class, 'unreact']);
        Route::post('/messages/{message}/bookmark', [ChatController::class, 'bookmark']);
        Route::delete('/messages/{message}/bookmark', [ChatController::class, 'unbookmark']);
        Route::get('/bookmarks', [ChatController::class, 'bookmarks']);
        Route::post('/conversations', [ChatController::class, 'create']);
        Route::post('/messages/{message}/forward', [ChatController::class, 'forward']);

        Route::get('/chat-requests', [ChatController::class, 'requests']);
        Route::post('/chat-requests/{user}', [ChatRequestController::class, 'store'])->middleware('throttle:30,1,chat-requests');
        Route::post('/chat-requests/{chatRequest}/action', [ChatController::class, 'requestAction']);

        Route::get('/notifications', [AccountController::class, 'notifications']);
        Route::post('/notifications/read-all', [AccountController::class, 'markNotifications']);
        Route::get('/push-tokens', [PushController::class, 'index']);
        Route::post('/push-tokens', [PushController::class, 'store'])->middleware('throttle:20,1,push-token');
        Route::delete('/push-tokens', [PushController::class, 'destroy']);

        Route::get('/plans', [AccountController::class, 'plans']);
        Route::get('/plans/matrix', [AccountController::class, 'plansMatrix']);
        Route::get('/plans/features', [AccountController::class, 'plansFeatures']);
        Route::get('/subscriptions', [AccountController::class, 'subscriptions']);
        Route::get('/subscriptions/history', [AccountController::class, 'subscriptionHistory']);
        Route::post('/subscriptions/{subscription}/cancel', [AccountController::class, 'cancelSubscription']);

        Route::get('/payments', [AccountController::class, 'payments']);
        Route::get('/payments/summary', [AccountController::class, 'paymentSummary']);
        Route::get('/payments/{payment}', [AccountController::class, 'payment']);
        Route::get('/payments/{payment}/receipt', [AccountController::class, 'receipt']);
        Route::post('/payments/{payment}/retry', [AccountController::class, 'retry']);

        Route::get('/wallet', [AccountController::class, 'wallet']);
        Route::get('/wallet/summary', [AccountController::class, 'walletSummary']);
        Route::get('/wallet/transactions', [AccountController::class, 'transactions']);
        Route::get('/subscriptions/trial-eligibility', [AccountController::class, 'trialEligibility']);
        Route::post('/subscriptions/switch', [AccountController::class, 'switchSubscription']);
        Route::get('/credit-products', [AccountController::class, 'creditProducts']);
        Route::post('/wallet/spend', [AccountController::class, 'spend'])->middleware('throttle:30,1,wallet-spend');

        Route::post('/verification', [AccountController::class, 'verify'])->middleware('throttle:5,1,verification');
        Route::get('/verification/status', [AccountController::class, 'verificationStatus']);
        Route::post('/reports', [AccountController::class, 'report'])->middleware('throttle:10,1,reports');
        Route::post('/blocks/{user}', [AccountController::class, 'block']);
        Route::delete('/blocks/{user}', [AccountController::class, 'unblock']);
        Route::get('/blocks', [AccountController::class, 'blocks']);

        Route::get('/gifts', [AccountController::class, 'gifts']);
        Route::get('/gifts/leaderboard', [AccountController::class, 'giftsLeaderboard']);
        Route::get('/gifts/trending', [AccountController::class, 'giftsTrending']);
        Route::post('/gifts/send', [AccountController::class, 'storeGift'])->middleware('throttle:10,1,gift-send');
        Route::get('/gifts/received', [AccountController::class, 'giftsReceived']);
        Route::get('/gifts/sent', [AccountController::class, 'giftsSent']);
        Route::get('/gifts/stats', [AccountController::class, 'giftsStats']);
        Route::post('/boost', [AccountController::class, 'boost']);
        Route::get('/boost/status', [AccountController::class, 'boostStatus']);
        Route::get('/boosts/history', [AccountController::class, 'boostHistory']);
        Route::post('/coupons/quote', [AccountController::class, 'quoteCoupon']);
        Route::post('/checkout', [AccountController::class, 'checkout'])->middleware('throttle:30,1,checkout');
        Route::post('/checkout/quote', [AccountController::class, 'checkoutQuote'])->middleware('throttle:30,1,checkout');
        Route::get('/ai/replies/{conversation}', [AccountController::class, 'suggestedReplies']);
        Route::post('/ai/matchmaker', [AccountController::class, 'matchmaker']);
        Route::post('/ai/rewrite', [AiAssistantController::class, 'rewrite'])->middleware('throttle:20,1,ai-rewrite');
        Route::get('/ai/taaruf-topics/{user}', [AiAssistantController::class, 'taarufTopics'])->middleware('throttle:20,1,ai-topics');
        Route::get('/ai/openers/{user}', [AccountController::class, 'aiOpeners']);
        Route::get('/ai/digest/{conversation}', [AccountController::class, 'aiDigest']);
        Route::get('/ai/profile-tips', [AiAssistantController::class, 'profileTips'])->middleware('throttle:20,1,ai-topics');
        Route::get('/ads', [SafetyController::class, 'ads']);
        Route::get('/ads/stats', [SafetyController::class, 'adStats']);
        Route::post('/ads/{ad}/impression', [SafetyController::class, 'adImpression']);
        Route::post('/ads/{ad}/click', [SafetyController::class, 'adClick']);
        Route::get('/settings', [AccountController::class, 'settings']);

        Route::middleware('brand.feature:community')->group(function () {
            Route::get('/blog', [BlogController::class, 'index']);
            Route::get('/blog/popular', [BlogController::class, 'popular']);
            Route::get('/blog/search', [BlogController::class, 'search']);
            Route::get('/blog/{slug}', [BlogController::class, 'show']);
            Route::get('/blog/{slug}/related', [BlogController::class, 'related']);
            Route::get('/forums', [ForumController::class, 'index']);
            Route::get('/forums/search', [ForumController::class, 'search']);
            Route::get('/forums/trending', [ForumController::class, 'trending']);
            Route::get('/forums/popular', [ForumController::class, 'popular']);
            Route::get('/forums/{slug}', [ForumController::class, 'threads']);
            Route::post('/forums/{slug}/threads', [ForumController::class, 'storeThread'])->middleware('throttle:10,1,forum-threads');
            Route::get('/forum-threads/{thread}', [ForumController::class, 'show']);
            Route::post('/forum-threads/{thread}/replies', [ForumController::class, 'reply'])->middleware('throttle:30,1,forum-replies');
            Route::put('/forum-threads/{thread}', [ForumController::class, 'updateThread']);
            Route::delete('/forum-threads/{thread}', [ForumController::class, 'destroyThread']);
            Route::put('/forum-replies/{reply}', [ForumController::class, 'updateReply']);
            Route::delete('/forum-replies/{reply}', [ForumController::class, 'destroyReply']);
        });

        Route::middleware('brand.feature:events')->group(function () {
            Route::get('/events/upcoming', [EventController::class, 'upcoming']);
            Route::get('/events/mine', [EventController::class, 'mine']);
            Route::get('/events', [EventController::class, 'index']);
            Route::get('/events/{event}', [EventController::class, 'show']);
            Route::post('/events/{event}/join', [EventController::class, 'join'])->middleware('throttle:20,1,events');
            Route::post('/events/{event}/leave', [EventController::class, 'leave'])->middleware('throttle:20,1,events');
            Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->middleware('throttle:20,1,events');
            Route::get('/events/{event}/attendees', [EventController::class, 'attendees']);
            Route::get('/events/{event}/suggested', [EventController::class, 'suggested']);
        });

        Route::middleware('brand.feature:taaruf')->group(function () {
            Route::get('/courtships', [BiroJodohController::class, 'courtships']);
            Route::post('/courtships', [BiroJodohController::class, 'startCourtship'])->middleware('throttle:10,1,courtships');
            Route::get('/courtships/journey/{partner}', [BiroJodohController::class, 'journey']);
            Route::get('/courtships/{courtship}', [BiroJodohController::class, 'showCourtship']);
            Route::post('/courtships/{courtship}/advance', [BiroJodohController::class, 'advanceCourtship']);
            Route::post('/courtships/{courtship}/withdraw', [BiroJodohController::class, 'withdrawCourtship']);
            Route::put('/courtships/{courtship}/guardian', [BiroJodohController::class, 'setGuardian']);
            Route::post('/courtships/{courtship}/guardian/approve', [BiroJodohController::class, 'approveGuardian']);
            Route::post('/courtships/{courtship}/chaperone', [BiroJodohController::class, 'addChaperone']);
            Route::delete('/courtships/{courtship}/chaperone', [BiroJodohController::class, 'removeChaperone']);
            Route::get('/compatibility-reports', [BiroJodohController::class, 'reports']);
            Route::post('/compatibility-reports', [BiroJodohController::class, 'generateReport'])->middleware('throttle:20,1,compatibility-reports');
            Route::get('/compatibility-reports/{report}', [BiroJodohController::class, 'showReport']);
            Route::get('/success-stories', [BiroJodohController::class, 'stories']);
            Route::get('/success-stories/mine', [BiroJodohController::class, 'myStories']);
            Route::post('/success-stories', [BiroJodohController::class, 'submitStory'])->middleware('throttle:5,1,success-stories');
            Route::middleware('brand.feature:counselor')->group(function () {
                Route::get('/counselors', [BiroJodohController::class, 'counselors']);
                Route::post('/consultations', [BiroJodohController::class, 'bookConsultation'])->middleware('throttle:10,1,consultations');
                Route::get('/consultations', [BiroJodohController::class, 'consultations']);
                Route::get('/consultations/counseling', [BiroJodohController::class, 'counselorBookings']);
                Route::post('/consultations/{consultation}/confirm', [BiroJodohController::class, 'confirmConsultation']);
                Route::post('/consultations/{consultation}/complete', [BiroJodohController::class, 'completeConsultation']);
                Route::post('/consultations/{consultation}/cancel', [BiroJodohController::class, 'cancelConsultation']);
            });
        });

        // Staff overview for dashboards / monitoring clients.
        Route::get('/admin/overview', [AdminController::class, 'overview'])
            ->middleware('role:admin,superadmin');
        Route::get('/admin/users/export', [UserController::class, 'export'])->middleware('role:admin,superadmin');
        Route::post('/admin/users/impersonate', [UserController::class, 'impersonate'])->middleware('role:admin,superadmin');
        Route::post('/admin/users/stop-impersonate', [UserController::class, 'stopImpersonate'])->middleware('role:admin,superadmin');
        // Payment gateways (masked secrets, Crypt at rest) + report resolution.
        Route::get('/admin/gateways', [AdminGatewayController::class, 'index'])->middleware('role:admin,superadmin');
        Route::post('/admin/gateways/{gateway}/toggle', [AdminGatewayController::class, 'toggle'])->middleware('role:admin,superadmin');
        Route::post('/admin/gateways/{gateway}/credentials', [AdminGatewayController::class, 'credentials'])->middleware('role:superadmin');
        Route::post('/admin/gateways/{gateway}/priority', [AdminGatewayController::class, 'priority'])->middleware('role:admin,superadmin');
        Route::post('/admin/reports/{report}/resolve', [AdminModerationController::class, 'resolveReport'])->middleware('role:moderator,admin,superadmin');
    });
});
