<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminOverviewController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('admin');
        $user = $request->user();
        if (! $user->isAdmin()) {
            abort(403, 'Admin only.');
        }

        return response()->json([
            'users_total' => User::count(),
            'reports_pending' => Report::where('status', 'pending')->count(),
        ]);
    }
}
