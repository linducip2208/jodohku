<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $q = trim((string) $request->input('q', ''));
        $results = $q !== '' ? $search->search($request->user(), $q, 10) : null;

        return $request->wantsJson()
            ? response()->json($results ?? [])
            : view('member.search.index', ['q' => $q, 'results' => $results]);
    }
}
