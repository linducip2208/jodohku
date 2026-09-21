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
use App\Http\Controllers\Member\ForumController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Auth namespace aliases (used by clients + tests).
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Payment gateway webhooks are public; the gateway HMAC signature is the
    // source of truth (never browser redirects). Idempotent via event_id.
    Route::post('/webhooks/{gateway}', WebhookController::class)
        ->middleware('throttle:60,1')
        ->name('webhooks.handle');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/profile', [ProfileController::class, 'me']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::get('/profile/{user}', [ProfileController::class, 'show'])->middleware('blocked');

        Route::get('/preferences', [PreferenceController::class, 'show']);
        Route::put('/preferences', [PreferenceController::class, 'update']);
        Route::get('/questions', [PreferenceController::class, 'questions']);
        Route::get('/questionnaire/answers', [PreferenceController::class, 'answers']);
        Route::post('/questionnaire/answers', [PreferenceController::class, 'answer']);

        Route::get('/discover', [DiscoveryController::class, 'discover']);
        Route::get('/matches', [DiscoveryController::class, 'matches']);
        Route::get('/matches/{user}/explain', [DiscoveryController::class, 'explain']);
        Route::post('/likes/{user}', [DiscoveryController::class, 'like']);
        Route::delete('/likes/{user}', [DiscoveryController::class, 'pass']);
        Route::post('/superlikes/{user}', [DiscoveryController::class, 'superlike']);
        Route::post('/favorites/{user}', [DiscoveryController::class, 'favorite']);
        Route::delete('/favorites/{user}', [DiscoveryController::class, 'unfavorite']);
        Route::post('/rewind', [DiscoveryController::class, 'rewind']);

        Route::get('/conversations', [ChatController::class, 'conversations']);
        Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:30,1');
        Route::post('/conversations/{conversation}/read', [ChatController::class, 'read']);
        Route::patch('/messages/{message}', [ChatController::class, 'edit']);
        Route::delete('/messages/{message}', [ChatController::class, 'delete']);

        Route::get('/chat-requests', [ChatController::class, 'requests']);
        Route::post('/chat-requests/{chatRequest}/action', [ChatController::class, 'requestAction']);

        Route::get('/notifications', [AccountController::class, 'notifications']);
        Route::post('/notifications/read-all', [AccountController::class, 'markNotifications']);

        Route::get('/plans', [AccountController::class, 'plans']);
        Route::get('/subscriptions', [AccountController::class, 'subscriptions']);
        Route::post('/checkout', [AccountController::class, 'checkout'])->middleware('throttle:10,1');
        Route::post('/subscriptions/{subscription}/cancel', [AccountController::class, 'cancelSubscription']);
        Route::get('/payments', [AccountController::class, 'payments']);
        Route::get('/payments/{payment}', [AccountController::class, 'payment']);

        Route::get('/wallet', [AccountController::class, 'wallet']);
        Route::get('/credit-products', [AccountController::class, 'creditProducts']);
        Route::post('/wallet/spend', [AccountController::class, 'spend'])->middleware('throttle:30,1');

        Route::post('/verification', [AccountController::class, 'verify'])->middleware('throttle:5,1');
        Route::post('/reports', [AccountController::class, 'report'])->middleware('throttle:10,1');
        Route::post('/blocks/{user}', [AccountController::class, 'block']);
        Route::delete('/blocks/{user}', [AccountController::class, 'unblock']);
        Route::get('/blocks', [AccountController::class, 'blocks']);

        Route::get('/gifts', [AccountController::class, 'gifts']);
        Route::post('/boost', [AccountController::class, 'boost']);
        Route::get('/ai/replies/{conversation}', [AccountController::class, 'suggestedReplies']);
        Route::post('/ai/matchmaker', [AccountController::class, 'matchmaker']);
        Route::get('/settings', [AccountController::class, 'settings']);

        Route::get('/blog', [BlogController::class, 'index']);
        Route::get('/blog/{slug}', [BlogController::class, 'show']);
        Route::get('/forums', [ForumController::class, 'index']);
        Route::get('/forums/{slug}', [ForumController::class, 'threads']);
        Route::post('/forums/{slug}/threads', [ForumController::class, 'storeThread'])->middleware('throttle:10,1');
        Route::get('/forum-threads/{thread}', [ForumController::class, 'show']);
        Route::post('/forum-threads/{thread}/replies', [ForumController::class, 'reply'])->middleware('throttle:30,1');

        // Staff overview for dashboards / monitoring clients.
        Route::get('/admin/overview', [AdminController::class, 'overview'])
            ->middleware('role:admin,superadmin');
    });
});
