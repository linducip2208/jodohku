<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlag
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        if (! config('jodohku.features.'.$flag, false)) {
            abort(404, 'Feature disabled.');
        }

        return $next($request);
    }
}
