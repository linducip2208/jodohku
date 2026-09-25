<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\SavedFilter;
use Illuminate\Http\Request;

class SavedFilterController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'filters' => ['required', 'array'],
        ]);
        $filters = array_intersect_key(
            array_filter($data['filters'], fn ($v) => $v !== null && $v !== '' && $v !== []),
            array_flip(SavedFilter::ALLOWED)
        );
        abort_unless(! empty($filters), 422, 'Filter kosong.');
        abort_unless(SavedFilter::where('user_id', $request->user()->id)->count() < 10, 422, 'Maksimal 10 filter tersimpan.');

        $saved = SavedFilter::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'filters' => $filters,
        ]);

        return $request->wantsJson()
            ? response()->json($saved, 201)
            : back()->with('status', 'Filter "'.$saved->name.'" tersimpan.');
    }

    public function destroy(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        $savedFilter->delete();

        return $request->wantsJson()
            ? response()->json(['message' => 'Deleted.'])
            : back()->with('status', 'Filter tersimpan dihapus.');
    }
}
