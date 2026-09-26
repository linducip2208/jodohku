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
 * Also applies the brand mail sender for sync (non-queued) emails.
 */
class ResolveBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $service = app(BrandService::class);
            // Staff preview override: ?preview_brand={id} (unsaved drafts via session).
            $previewId = $request->query('preview_brand');
            if ($previewId && $request->user()?->isStaff()) {
                $preview = Brand::find($previewId);
                if ($preview) {
                    $this->apply($request, $service, $preview);

                    return $next($request);
                }
                $draft = $request->session()->get('brand_wizard_draft');
                if (is_array($draft)) {
                    $this->apply($request, $service, new Brand([
                        'slug' => 'preview', 'name' => $draft['name'] ?? 'Preview',
                        'tagline' => $draft['tagline'] ?? null,
                        'primary_color' => $draft['primary_color'] ?? '#f43f5e',
                        'secondary_color' => $draft['secondary_color'] ?? '#8b5cf6',
                    ]));

                    return $next($request);
                }
            }
            $this->apply($request, $service, $service->current());
        } catch (\Throwable) {
        }

        return $next($request);
    }

    protected function apply(Request $request, BrandService $service, ?Brand $brand): void
    {
        $theme = $service->theme($brand);
        view()->share('brandTheme', $theme);
        $request->attributes->set('brandTheme', $theme);
        // Per-brand sender for sync emails (queued jobs use defaults;
        // see WHITELABEL.md). Address must be set, else keep default.
        if ($brand?->mail_from_address && filter_var($brand->mail_from_address, FILTER_VALIDATE_EMAIL)) {
            config(['mail.from.address' => $brand->mail_from_address]);
            config(['mail.from.name' => $brand->mail_from_name ?: $brand->name]);
        }
    }
}
