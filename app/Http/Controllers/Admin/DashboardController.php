<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\CreditTransaction;
use App\Models\Message;
use App\Models\OperatorAssignment;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        [$metrics, $charts] = $this->snapshot();

        return $request->wantsJson()
            ? response()->json(['metrics' => $metrics, 'charts' => $charts])
            : view('admin.dashboard', ['metrics' => $metrics, 'charts' => $charts]);
    }

    protected function snapshot(): array
    {
        $metrics = Cache::remember('admin.dashboard.metrics', 60, function () {
            $revenue = (float) Payment::where('status', 'paid')->sum('amount');

            return [
                'users' => User::count(),
                'users_7d' => User::where('created_at', '>', now()->subDays(7))->count(),
                'online' => User::where('is_online', true)->count(),
                'matches' => UserMatch::count(),
                'messages' => Message::count(),
                'reports' => Report::count(),
                'premium' => User::where('is_premium', true)->count(),
                'revenue' => $revenue,
                'revenue_formatted' => 'Rp'.number_format($revenue, 0, ',', '.'),
                'credits' => (float) CreditTransaction::sum('amount'),
                'virtual' => User::where('account_type', 'virtual')->count(),
                'ai_logs' => AiUsageLog::count(),
                'operators' => OperatorAssignment::count(),
            ];
        });

        $charts = Cache::remember('admin.dashboard.charts', 60, function () {
            $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i));

            return [
                'labels' => $days->map(fn ($d) => $d->format('d M'))->values()->all(),
                'registrations' => $days->map(fn ($d) => User::whereDate('created_at', $d->toDateString())->count())->values()->all(),
                'matches' => $days->map(fn ($d) => UserMatch::whereDate('matched_at', $d->toDateString())->count())->values()->all(),
                'messages' => $days->map(fn ($d) => Message::whereDate('created_at', $d->toDateString())->count())->values()->all(),
            ];
        });

        return [$metrics, $charts];
    }
}
