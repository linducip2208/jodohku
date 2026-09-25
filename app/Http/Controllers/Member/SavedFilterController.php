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

    /** Rename a saved filter (owner only). */
    public function update(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $savedFilter->update(['name' => trim($data['name'])]);

        return $request->wantsJson()
            ? response()->json($savedFilter->fresh())
            : back()->with('status', 'Filter diganti nama.');
    }

    /** Duplicate a saved filter (owner only, respects 10-filter cap). */
    public function duplicate(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        abort_unless(SavedFilter::where('user_id', $request->user()->id)->count() < 10, 422, 'Maksimal 10 filter tersimpan.');
        $copy = SavedFilter::create([
            'user_id' => $request->user()->id,
            'name' => mb_substr($savedFilter->name.' (salinan)', 0, 60),
            'filters' => $savedFilter->filters,
        ]);

        return $request->wantsJson()
            ? response()->json($copy, 201)
            : back()->with('status', 'Filter diduplikasi.');
    }

    /** Set default filter (owner only, single default per user). */
    public function makeDefault(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        SavedFilter::where('user_id', $request->user()->id)->update(['is_default' => false]);
        $savedFilter->update(['is_default' => true]);

        return $request->wantsJson()
            ? response()->json($savedFilter->fresh())
            : back()->with('status', 'Filter default diperbarui.');
    }
}
