<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index()
    {
        return response()->json(Ad::orderByDesc('id')->paginate(25));
    }

    public function store(Request $request, AuditService $audit)
    {
        $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'placement' => ['nullable', 'string', 'max:60'],
            'image_path' => ['nullable', 'string', 'max:512'],
            'target_url' => ['nullable', 'url', 'max:512'],
            'status' => ['nullable', 'string'],
        ]);
        $ad = Ad::create($request->all());
        $audit->log('admin.ad.created', $request->user(), $ad);

        return response()->json($ad, 201);
    }

    public function update(Request $request, Ad $ad, AuditService $audit)
    {
        $ad->update($request->all());
        $audit->log('admin.ad.updated', $request->user(), $ad);

        return response()->json($ad->fresh());
    }

    public function destroy(Request $request, Ad $ad, AuditService $audit)
    {
        $ad->delete();
        $audit->log('admin.ad.deleted', $request->user(), $ad);

        return response()->json(['message' => 'Deleted.']);
    }
}
