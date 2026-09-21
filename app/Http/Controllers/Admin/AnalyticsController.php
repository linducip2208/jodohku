<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        return response()->json([
            'registrations' => User::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
            'matches' => UserMatch::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
            'messages' => Message::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
            'revenue' => Payment::selectRaw('DATE(created_at) d, SUM(total_amount) c')->where('status', 'paid')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
        ]);
    }

    public function funnel(Request $request)
    {
        $total = User::count();

        return response()->json([
            'registered' => $total,
            'with_photo' => User::has('photos')->count(),
            'with_questionnaire' => User::has('questionnaireAnswers')->count(),
            'with_match' => User::where(fn ($q) => $q->whereHas('matchesA')->orWhereHas('matchesB'))->count(),
            'paying' => User::where('is_premium', true)->count(),
        ]);
    }
}
