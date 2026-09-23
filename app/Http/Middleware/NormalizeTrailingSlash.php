<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Canonical trailing-slash handling: GET/HEAD URLs ending in "/" (except
 * the root and real files) 301-redirect to the slashless canonical form so
 * crawlers never split ranking signals across duplicates.
 */
class NormalizeTrailingSlash
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }
        $path = $request->getPathInfo();
        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            $query = $request->getQueryString();
            $target = rtrim($request->url(), '/').($query ? '?'.$query : '');

            return redirect($target, 301);
        }

        return $next($request);
    }
}
