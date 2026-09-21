<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $coupons = Coupon::withCount('redemptions')->latest('id')->paginate(25);

        return $request->wantsJson()
            ? response()->json($coupons)
            : view('admin.coupons', ['coupons' => $coupons]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $data['code'] = strtoupper(trim($data['code']));
        if (($data['type'] ?? '') === 'percent' && (float) $data['value'] > 100) {
            return response()->json(['message' => 'Percent discount cannot exceed 100.'], 422);
        }
        $coupon = Coupon::create($data);
        $audit->log('admin.coupon.created', $request->user(), $coupon, [], $data);

        return response()->json($coupon, 201);
    }

    public function update(Request $request, Coupon $coupon, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $before = $coupon->only(array_keys($data));
        $coupon->update($data);
        $audit->log('admin.coupon.updated', $request->user(), $coupon, $before, $data);

        return response()->json($coupon->fresh());
    }

    public function toggle(Request $request, Coupon $coupon, AuditService $audit)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        $audit->log('admin.coupon.toggled', $request->user(), $coupon, [], ['is_active' => $coupon->is_active]);

        return response()->json($coupon->fresh());
    }

    public function redemptions(Request $request)
    {
        return response()->json(
            \App\Models\CouponRedemption::with(['coupon', 'user', 'payment'])
                ->latest('id')->paginate(25)
        );
    }
}
