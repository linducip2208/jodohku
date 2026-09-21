<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;

class AiUsageController extends Controller
{
    public function index(Request $request)
    {
        $items = AiUsageLog::with('user')->latest('id')->paginate(25);
        $summary = [
            'tokens_30d' => AiUsageLog::where('created_at', '>', now()->subDays(30))->sum('tokens'),
            'cost_30d' => AiUsageLog::where('created_at', '>', now()->subDays(30))->sum('cost'),
            'by_purpose' => AiUsageLog::selectRaw('purpose, SUM(tokens) tokens, SUM(cost) cost, COUNT(*) n')
                ->where('created_at', '>', now()->subDays(30))->groupBy('purpose')->get(),
        ];

        return response()->json(['logs' => $items, 'summary' => $summary]);
    }
}
