<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Message;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $metrics = [
            'dau' => User::where('last_active_at', '>', now()->subDay())->count(),
            'wau' => User::where('last_active_at', '>', now()->subWeek())->count(),
            'mau' => User::where('last_active_at', '>', now()->subMonth())->count(),
            'registrations_7d' => User::where('created_at', '>', now()->subDays(7))->count(),
            'matches_7d' => UserMatch::where('created_at', '>', now()->subDays(7))->count(),
            'messages_7d' => Message::where('created_at', '>', now()->subDays(7))->count(),
            'revenue_30d' => Payment::where('status', 'paid')->where('created_at', '>', now()->subDays(30))->sum('total_amount'),
            'ai_cost_30d' => AiUsageLog::where('created_at', '>', now()->subDays(30))->sum('cost'),
        ];
        $charts = [
            'registrations' => User::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', now()->subDays(30))->groupBy('d')->orderBy('d')->get(),
            'messages' => Message::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', now()->subDays(30))->groupBy('d')->orderBy('d')->get(),
            'revenue' => Payment::selectRaw('DATE(created_at) d, SUM(total_amount) c')->where('status', 'paid')->where('created_at', '>', now()->subDays(30))->groupBy('d')->orderBy('d')->get(),
        ];

        return $request->wantsJson()
            ? response()->json(['metrics' => $metrics, 'charts' => $charts])
            : view('admin.dashboard', ['metrics' => $metrics, 'charts' => $charts]);
    }
}
