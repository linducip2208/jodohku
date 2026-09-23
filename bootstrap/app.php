<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureBlocked;
use App\Http\Middleware\FeatureFlag;
use App\Http\Middleware\NormalizeTrailingSlash;
use App\Http\Middleware\PremiumOnly;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.account' => EnsureActiveAccount::class,
            'blocked' => EnsureBlocked::class,
            'role' => CheckRole::class,
            'feature' => FeatureFlag::class,
            'premium' => PremiumOnly::class,
        ]);
        $middleware->web(append: [
            NormalizeTrailingSlash::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
