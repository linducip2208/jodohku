<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Rejects authenticated users whose account is no longer usable
 *  (suspended/banned/inactive/deleted), even with a previously issued token. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->loginBlockedReason()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $user->loginBlockedReason()], 403);
            }
            Auth::guard('web')->logout();

            return redirect()->route('login')->withErrors(['email' => $user->loginBlockedReason()]);
        }

        return $next($request);
    }
}
