<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Services\BrandService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve whitelabel brand by request domain and share it to all views
 * as $brandTheme (with Jodohku fallback when no brand matches).
 */
class ResolveBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Staff preview override: ?preview_brand={id} (unsaved drafts via session).
            $previewId = $request->query('preview_brand');
            if ($previewId && $request->user()?->isStaff()) {
                $preview = Brand::find($previewId);
                if ($preview) {
                    $theme = app(BrandService::class)->theme($preview);
                    view()->share('brandTheme', $theme);
                    $request->attributes->set('brandTheme', $theme);

                    return $next($request);
                }
                $draft = $request->session()->get('brand_wizard_draft');
                if (is_array($draft)) {
                    $ghost = new Brand([
                        'slug' => 'preview', 'name' => $draft['name'] ?? 'Preview',
                        'tagline' => $draft['tagline'] ?? null,
                        'primary_color' => $draft['primary_color'] ?? '#f43f5e',
                        'secondary_color' => $draft['secondary_color'] ?? '#8b5cf6',
                    ]);
                    $theme = app(BrandService::class)->theme($ghost);
                    view()->share('brandTheme', $theme);
                    $request->attributes->set('brandTheme', $theme);

                    return $next($request);
                }
            }
            $theme = app(BrandService::class)->theme();
            view()->share('brandTheme', $theme);
            $request->attributes->set('brandTheme', $theme);
        } catch (\Throwable) {
        }

        return $next($request);
    }
}
