<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Boost;
use App\Services\AuditService;
use Illuminate\Http\Request;

class BoostAdminController extends Controller
{
    public function index()
    {
        return response()->json(Boost::with('user')->latest('id')->paginate(25));
    }

    public function pricing(Request $request, AuditService $audit)
    {
        if ($request->isMethod('post') || $request->isMethod('put')) {
            $request->validate(['credit_price' => ['required', 'integer', 'min:1']]);
            \App\Models\Setting::updateOrCreate(['key' => 'boost.credit_price'], ['value' => (string) $request->input('credit_price'), 'group' => 'boost']);
            $audit->log('admin.boost.pricing', $request->user());

            return response()->json(['credit_price' => $request->input('credit_price')]);
        }

        return response()->json(['credit_price' => \App\Models\Setting::where('key', 'boost.credit_price')->first()?->value ?? 50]);
    }
}
