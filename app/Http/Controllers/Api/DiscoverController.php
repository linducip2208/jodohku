<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiscoveryService;
use Illuminate\Http\Request;

class DiscoverController extends Controller
{
    public function __invoke(Request $request, DiscoveryService $discovery)
    {
        $user = $request->user();
        $filters = $request->only(['gender', 'city', 'min_age', 'max_age', 'verified', 'online', 'sort']);
        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));

        $paginator = $discovery->discover($user, $filters, $perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($u) => [
                'id' => $u->id,
                'display_name' => $u->displayName(),
                'city' => $u->city,
                'province' => $u->province,
                'is_verified' => (bool) $u->is_verified,
                'is_premium' => (bool) $u->is_premium,
                'compatibility_score' => $u->getAttribute('compatibility_score'),
            ])->values(),
            'meta' => [
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }
}
