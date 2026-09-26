<?php

namespace App\Http\Middleware;

use App\Services\BrandService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce per-brand feature flags (whitelabel Tier 3/P1).
 *
 * Usage: ->middleware('brand.feature:taaruf')
 * Without an active brand (default Jodohku), falls back to the global
 * config flag (config/jodohku.php features or FEATURE_* env).
 * Disabled → 404 (same as global FeatureFlag: no existence leaks).
 */
class EnsureBrandFeature
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $on = (bool) config('jodohku.features.'.$flag, true);
        try {
            $brand = app(BrandService::class)->current();
            if ($brand) {
                $on = $brand->featureOn($flag, $on);
            }
        } catch (\Throwable) {
        }
        abort_unless($on, 404, 'Feature disabled.');

        return $next($request);
    }
}
