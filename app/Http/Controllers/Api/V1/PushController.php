<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            PushToken::where('user_id', $request->user()->id)->live()->get(['id', 'platform', 'created_at'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'in:web,android,ios'],
            'token' => ['required', 'string', 'max:500'],
            'meta' => ['nullable', 'array'],
        ]);
        // One device token belongs to one user: steal on re-login.
        PushToken::where('token', $data['token'])->delete();
        $row = PushToken::create([
            'user_id' => $request->user()->id,
            'platform' => $data['platform'],
            'token' => $data['token'],
            'meta' => $data['meta'] ?? null,
        ]);

        return response()->json($row, 201);
    }

    public function destroy(Request $request)
    {
        $request->validate(['token' => ['required', 'string', 'max:500']]);
        PushToken::where('user_id', $request->user()->id)->where('token', $request->input('token'))->delete();

        return response()->json(['message' => 'Token removed.']);
    }
}
