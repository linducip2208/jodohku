<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SavedFilter;
use Illuminate\Http\Request;

/**
 * Saved discovery filters for future Flutter clients.
 * Mirrors Member\SavedFilterController (store/update/duplicate/default/destroy).
 */
class SavedFilterController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            SavedFilter::where('user_id', $request->user()->id)->latest('id')->paginate(20)
        );
    }

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

        return response()->json(SavedFilter::create([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'filters' => $filters,
        ]), 201);
    }

    public function update(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $savedFilter->update(['name' => trim($data['name'])]);

        return response()->json($savedFilter->fresh());
    }

    public function duplicate(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        abort_unless(SavedFilter::where('user_id', $request->user()->id)->count() < 10, 422, 'Maksimal 10 filter tersimpan.');

        return response()->json(SavedFilter::create([
            'user_id' => $request->user()->id,
            'name' => mb_substr($savedFilter->name.' (salinan)', 0, 60),
            'filters' => $savedFilter->filters,
        ]), 201);
    }

    public function makeDefault(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        SavedFilter::where('user_id', $request->user()->id)->update(['is_default' => false]);
        $savedFilter->update(['is_default' => true]);

        return response()->json($savedFilter->fresh());
    }

    public function destroy(Request $request, SavedFilter $savedFilter)
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        $savedFilter->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
