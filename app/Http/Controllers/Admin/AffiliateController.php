<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $accounts = AffiliateAccount::with('user:id,display_name,name,email')->latest('id')->paginate(25);
        $pending = AffiliateCommission::with(['account.user:id,display_name,name', 'payment:id,invoice_number,total_amount'])
            ->where('status', AffiliateCommission::STATUS_PENDING)->latest('id')->paginate(25);

        return $request->wantsJson()
            ? response()->json(['accounts' => $accounts, 'pending_commissions' => $pending])
            : view('admin.affiliates', ['accounts' => $accounts, 'pending_commissions' => $pending]);
    }

    public function decideAccount(Request $request, AffiliateAccount $account, AuditService $audit)
    {
        $data = $request->validate(['action' => ['required', 'string', 'in:approve,suspend']]);
        $account->update(['status' => $data['action'] === 'approve'
            ? AffiliateAccount::STATUS_APPROVED : AffiliateAccount::STATUS_SUSPENDED]);
        $audit->log('admin.affiliate.'.$data['action'], $request->user(), $account);

        return $request->wantsJson() ? response()->json($account->fresh()) : back()->with('status', 'Akun afiliasi diperbarui.');
    }

    public function decideCommission(Request $request, AffiliateCommission $commission, AuditService $audit)
    {
        $data = $request->validate(['action' => ['required', 'string', 'in:approve,pay,reject']]);
        $status = match ($data['action']) {
            'approve' => AffiliateCommission::STATUS_APPROVED,
            'pay' => AffiliateCommission::STATUS_PAID,
            default => AffiliateCommission::STATUS_REJECTED,
        };
        $commission->update(['status' => $status]);
        $audit->log('admin.affiliate.commission_'.$data['action'], $request->user(), $commission);

        return $request->wantsJson() ? response()->json($commission->fresh()) : back()->with('status', 'Komisi diperbarui.');
    }
}
