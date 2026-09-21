<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\AdService;
use Illuminate\Http\Request;

class SafetyController extends Controller
{
    public function index(Request $request)
    {
        return $request->wantsJson()
            ? response()->json(['tips' => config('jodohku.safety_tips', []), 'support_email' => config('mail.support', 'support@jodohku.id')])
            : view('member.safety.center');
    }

    public function ads(Request $request, AdService $ads)
    {
        $request->validate(['placement' => ['nullable', 'string', 'max:60']]);
        $items = $ads->servable($request->input('placement', 'feed'));

        return response()->json($items);
    }

    public function adImpression(Request $request, Ad $ad, AdService $ads)
    {
        $ads->impression($ad, $request->user(), $request->input('placement', 'feed'));

        return response()->json(['ok' => true]);
    }

    public function adClick(Request $request, Ad $ad, AdService $ads)
    {
        $ads->click($ad, $request->user(), $request->input('placement', 'feed'));

        return response()->json(['ok' => true]);
    }
}
