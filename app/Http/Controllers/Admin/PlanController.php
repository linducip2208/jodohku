<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return response()->json(MembershipPlan::orderBy('sort_order')->get());
    }

    public function store(Request $request, AuditService $audit)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:60', 'unique:membership_plans,code'],
            'name' => ['required', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['nullable', 'boolean'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
        ]);
        $plan = MembershipPlan::create($request->all());
        $audit->log('admin.plan.created', $request->user(), $plan);

        return response()->json($plan, 201);
    }

    public function update(Request $request, MembershipPlan $plan, AuditService $audit)
    {
        $plan->update($request->all());
        $audit->log('admin.plan.updated', $request->user(), $plan);

        return response()->json($plan->fresh());
    }

    public function destroy(Request $request, MembershipPlan $plan, AuditService $audit)
    {
        $plan->delete();
        $audit->log('admin.plan.deleted', $request->user(), $plan);

        return response()->json(['message' => 'Deleted.']);
    }
}
