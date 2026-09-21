<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisteredUserController extends Controller
{
    public function create(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Register endpoint.']);
        }

        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $u = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->string('password')),
                'date_of_birth' => $request->date('date_of_birth'),
                'gender' => $request->input('gender'),
                'city' => $request->input('city'),
                'province' => $request->input('province'),
            ]);
            Profile::firstOrCreate(['user_id' => $u->id]);
            $u->partnerPreference()->firstOrCreate([]);
            $u->creditWallet()->firstOrCreate([], ['balance' => 0]);

            return $u;
        });

        event(new UserRegistered($user));

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Registered.', 'user_id' => $user->id], 201);
        }

        return redirect()->intended('/app');
    }
}
