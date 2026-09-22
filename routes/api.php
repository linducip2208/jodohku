<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DiscoveryController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Member\BlogController;
use App\Http\Controllers\Member\EventController;
use App\Http\Controllers\Member\ForumController;
use App\Http\Controllers\Member\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
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
        Route::post('/profile/photos', [\App\Http\Controllers\Member\ProfileController::class, 'photos']);
        Route::delete('/profile/photos/{photo}', [\App\Http\Controllers\Member\ProfileController::class, 'destroyPhoto']);
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

        Route::get('/discover', [DiscoveryController::class, 'discover']);
        Route::get('/discover/picks', [DiscoveryController::class, 'picks']);
        Route::post('/discover/picks/reset', [DiscoveryController::class, 'resetPicks']);
        Route::get('/matches', [DiscoveryController::class, 'matches']);
        Route::get('/matches/{user}/explain', [DiscoveryController::class, 'explain']);
        Route::post('/matches/batch-score', [DiscoveryController::class, 'batchScore']);
        Route::get('/matches/stats', [DiscoveryController::class, 'stats']);
        Route::get('/matches/history', [DiscoveryController::class, 'history']);
        Route::get('/who-liked', [\App\Http\Controllers\Member\MatchController::class, 'whoLiked']);
        Route::get('/visitors', [\App\Http\Controllers\Member\MatchController::class, 'visitors']);
        Route::post('/likes/{user}', [DiscoveryController::class, 'like']);
        Route::get('/likes/quota', [DiscoveryController::class, 'likeQuota']);
        Route::delete('/likes/{user}', [DiscoveryController::class, 'pass']);
        Route::post('/superlikes/{user}', [DiscoveryController::class, 'superlike']);
        Route::post('/favorites/{user}', [DiscoveryController::class, 'favorite']);
        Route::delete('/favorites/{user}', [DiscoveryController::class, 'unfavorite']);
        Route::post('/rewind', [DiscoveryController::class, 'rewind']);

        Route::get('/conversations', [ChatController::class, 'conversations']);
        Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:30,1,chat-send');
        Route::post('/chat/{conversation}/attachments', [ChatController::class, 'upload'])->middleware('throttle:30,1,chat-upload');
        Route::post('/conversations/{conversation}/attachments', [ChatController::class, 'upload'])->middleware('throttle:30,1,chat-upload');
        Route::get('/chat/conversations', [ChatController::class, 'conversations']);
        Route::get('/chat/conversations/search', [ChatController::class, 'search']);
        Route::get('/chat/overview', [ChatController::class, 'overview']);
        Route::get('/chat/{conversation}/stats', [ChatController::class, 'stats']);
        Route::post('/chat/{conversation}/clear-history', [ChatController::class, 'clearHistory']);
        Route::get('/chat/{conversation}/labels', [ChatController::class, 'labels']);
        Route::post('/chat/{conversation}/labels', [ChatController::class, 'addLabel']);
        Route::delete('/chat/{conversation}/labels/{labelId}', [ChatController::class, 'removeLabel']);
        Route::post('/chat/{conversation}/mark-all-read', [ChatController::class, 'markRead']);
        Route::post('/conversations/{conversation}/read', [ChatController::class, 'read']);
        Route::patch('/messages/{message}', [ChatController::class, 'edit']);
        Route::delete('/messages/{message}', [ChatController::class, 'delete']);
        Route::get('/messages/{message}/reactions', [ChatController::class, 'reactions']);
        Route::post('/conversations', [ChatController::class, 'create']);
        Route::post('/messages/{message}/forward', [ChatController::class, 'forward']);

        Route::get('/chat-requests', [ChatController::class, 'requests']);
        Route::post('/chat-requests/{user}', [\App\Http\Controllers\Member\ChatRequestController::class, 'store'])->middleware('throttle:30,1,chat-requests');
        Route::post('/chat-requests/{chatRequest}/action', [ChatController::class, 'requestAction']);

        Route::get('/notifications', [AccountController::class, 'notifications']);
        Route::post('/notifications/read-all', [AccountController::class, 'markNotifications']);

        Route::get('/plans', [AccountController::class, 'plans']);
        Route::get('/plans/matrix', [AccountController::class, 'plansMatrix']);
        Route::get('/plans/features', [AccountController::class, 'plansFeatures']);
        Route::get('/subscriptions', [AccountController::class, 'subscriptions']);
        Route::get('/subscriptions/history', [AccountController::class, 'subscriptionHistory']);
        Route::post('/subscriptions/{subscription}/cancel', [AccountController::class, 'cancelSubscription']);

        Route::get('/payments/{payment}', [AccountController::class, 'payment']);
        Route::get('/payments/{payment}/receipt', [AccountController::class, 'receipt']);
        Route::get('/payments/summary', [AccountController::class, 'paymentSummary']);
        Route::post('/payments/{payment}/retry', [AccountController::class, 'retry']);

        Route::get('/wallet', [AccountController::class, 'wallet']);
        Route::get('/wallet/transactions', [AccountController::class, 'transactions']);
        Route::get('/credit-products', [AccountController::class, 'creditProducts']);
        Route::post('/wallet/spend', [AccountController::class, 'spend'])->middleware('throttle:30,1,wallet-spend');

        Route::post('/verification', [AccountController::class, 'verify'])->middleware('throttle:5,1,verification');
        Route::post('/reports', [AccountController::class, 'report'])->middleware('throttle:10,1,reports');
        Route::post('/blocks/{user}', [AccountController::class, 'block']);
        Route::delete('/blocks/{user}', [AccountController::class, 'unblock']);
        Route::get('/blocks', [AccountController::class, 'blocks']);

        Route::get('/gifts', [AccountController::class, 'gifts']);
        Route::post('/gifts/send', [AccountController::class, 'storeGift'])->middleware('throttle:10,1,gift-send');
        Route::get('/gifts/received', [AccountController::class, 'giftsReceived']);
        Route::get('/gifts/sent', [AccountController::class, 'giftsSent']);
        Route::get('/gifts/stats', [AccountController::class, 'giftsStats']);
        Route::post('/boost', [AccountController::class, 'boost']);
        Route::post('/checkout', [AccountController::class, 'checkout'])->middleware('throttle:30,1,checkout');
        Route::get('/ai/replies/{conversation}', [AccountController::class, 'suggestedReplies']);
        Route::post('/ai/matchmaker', [AccountController::class, 'matchmaker']);
        Route::get('/ai/openers/{user}', [AccountController::class, 'aiOpeners']);
        Route::get('/ai/digest/{conversation}', [AccountController::class, 'aiDigest']);
        Route::get('/settings', [AccountController::class, 'settings']);

        Route::get('/blog', [BlogController::class, 'index']);
        Route::get('/blog/popular', [BlogController::class, 'popular']);
        Route::get('/blog/search', [BlogController::class, 'search']);
        Route::get('/blog/{slug}', [BlogController::class, 'show']);
        Route::get('/blog/{slug}/related', [BlogController::class, 'related']);
        Route::get('/forums', [ForumController::class, 'index']);
        Route::get('/forums/trending', [ForumController::class, 'trending']);
        Route::get('/forums/popular', [ForumController::class, 'popular']);
        Route::get('/forums/{slug}', [ForumController::class, 'threads']);
        Route::post('/forums/{slug}/threads', [ForumController::class, 'storeThread'])->middleware('throttle:10,1,forum-threads');
        Route::get('/forum-threads/{thread}', [ForumController::class, 'show']);
        Route::post('/forum-threads/{thread}/replies', [ForumController::class, 'reply'])->middleware('throttle:30,1,forum-replies');

        Route::get('/events/upcoming', [EventController::class, 'upcoming']);
        Route::get('/events/mine', [EventController::class, 'mine']);
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::post('/events/{event}/join', [EventController::class, 'join'])->middleware('throttle:20,1,events');
        Route::post('/events/{event}/leave', [EventController::class, 'leave'])->middleware('throttle:20,1,events');
        Route::get('/events/{event}/attendees', [EventController::class, 'attendees']);

        // Staff overview for dashboards / monitoring clients.
        Route::get('/admin/overview', [AdminController::class, 'overview'])
            ->middleware('role:admin,superadmin');
        Route::get('/admin/users/export', [\App\Http\Controllers\Admin\UserController::class, 'export'])->middleware('role:admin,superadmin');
        Route::post('/admin/users/impersonate', [\App\Http\Controllers\Admin\UserController::class, 'impersonate'])->middleware('role:admin,superadmin');
        Route::post('/admin/users/stop-impersonate', [\App\Http\Controllers\Admin\UserController::class, 'stopImpersonate'])->middleware('role:admin,superadmin');
    });
});
