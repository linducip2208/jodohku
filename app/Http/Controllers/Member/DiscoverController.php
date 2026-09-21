<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\DiscoveryService;
use Illuminate\Http\Request;

class DiscoverController extends Controller
{
    public function index(Request $request, DiscoveryService $discovery)
    {
        $request->validate([
            'gender' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'education' => ['nullable', 'string', 'max:160'],
            'verified' => ['nullable', 'boolean'],
            'online' => ['nullable', 'boolean'],
            'premium' => ['nullable', 'boolean'],
            'min_age' => ['nullable', 'integer', 'min:17', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:17', 'max:100'],
            'max_distance_km' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'sort' => ['nullable', 'string', 'in:compatibility,distance,active,newest,popularity'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $perPage = (int) ($request->input('per_page', 20));
        $result = $discovery->discover($request->user(), $request->only([
            'gender', 'city', 'education', 'verified', 'online', 'premium',
            'min_age', 'max_age', 'max_distance_km', 'sort',
        ]), $perPage, $request->query('cursor'));

        if ($request->wantsJson()) {
            return UserResource::collection($result)->response();
        }

        return view('member.discover', ['candidates' => $result]);
    }
}
