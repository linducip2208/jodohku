<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $role = strtolower((string) ($user->role?->value ?? $user->role));
        $allowed = array_map('strtolower', $roles);
        if (! in_array($role, $allowed, true)) {
            abort(403, 'Insufficient role.');
        }

        return $next($request);
    }
}
