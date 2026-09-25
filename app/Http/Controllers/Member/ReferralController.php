<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function dashboard(Request $request, ReferralService $referrals)
    {
        $data = $referrals->dashboard($request->user());
        $history = Referral::with('referred:id,display_name,name')
            ->where('referrer_id', $request->user()->id)->latest('id')->limit(50)->get();

        return $request->wantsJson()
            ? response()->json($data + ['history' => $history])
            : view('member.referral.dashboard', $data + ['history' => $history]);
    }

    public function applyAffiliate(Request $request, ReferralService $referrals)
    {
        $account = $referrals->applyAffiliate($request->user());

        return $request->wantsJson()
            ? response()->json($account, 201)
            : back()->with('status', 'Pengajuan afiliasi terkirim (kode '.$account->code.'). Menunggu persetujuan admin.');
    }
}
