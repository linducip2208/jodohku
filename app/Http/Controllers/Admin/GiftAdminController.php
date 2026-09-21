<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Services\AuditService;
use Illuminate\Http\Request;

class GiftAdminController extends Controller
{
    public function index()
    {
        return response()->json(Gift::orderBy('sort_order')->get());
    }

    public function store(Request $request, AuditService $audit)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:60', 'unique:gifts,code'],
            'name' => ['required', 'string', 'max:160'],
            'credit_price' => ['required', 'integer', 'min:1'],
        ]);
        $gift = Gift::create($request->all());
        $audit->log('admin.gift.created', $request->user(), $gift);

        return response()->json($gift, 201);
    }

    public function update(Request $request, Gift $gift, AuditService $audit)
    {
        $gift->update($request->all());
        $audit->log('admin.gift.updated', $request->user(), $gift);

        return response()->json($gift->fresh());
    }

    public function destroy(Request $request, Gift $gift, AuditService $audit)
    {
        $gift->delete();
        $audit->log('admin.gift.deleted', $request->user(), $gift);

        return response()->json(['message' => 'Deleted.']);
    }

    public function transactions()
    {
        return response()->json(GiftTransaction::with(['sender', 'receiver'])->latest('id')->paginate(25));
    }
}
