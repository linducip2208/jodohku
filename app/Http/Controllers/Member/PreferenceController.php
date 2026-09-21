<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerPreferenceRequest;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function show(Request $request)
    {
        $pref = $request->user()->partnerPreference()->firstOrCreate([]);

        return response()->json($pref);
    }

    public function update(PartnerPreferenceRequest $request)
    {
        $user = $request->user();
        $user->partnerPreference()->updateOrCreate([], $request->validated());

        return response()->json($user->partnerPreference()->first()->fresh());
    }
}
