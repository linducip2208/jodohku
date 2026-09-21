<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserUpdateRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with(['profile'])->latest('id');
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('display_name', 'like', "%{$q}%"));
        }
        foreach (['role', 'status', 'account_type'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }
        $users = $query->paginate(25);

        return $request->wantsJson() ? response()->json($users) : view('admin.users.index', ['users' => $users]);
    }

    public function show(Request $request, User $user)
    {
        $user->load(['profile', 'photos', 'subscriptions.plan', 'payments', 'creditWallet', 'verificationRequests']);

        return $request->wantsJson() ? response()->json($user) : view('admin.users.show', ['user' => $user]);
    }

    public function update(AdminUserUpdateRequest $request, User $user, AuditService $audit)
    {
        $old = $user->toArray();
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        $audit->log('admin.user.updated', $request->user(), $user, $old, $user->fresh()->toArray());

        return response()->json($user->fresh());
    }

    public function suspend(Request $request, User $user, AuditService $audit)
    {
        $user->update(['status' => UserStatus::Suspended]);
        $audit->log('admin.user.suspended', $request->user(), $user);

        return response()->json(['message' => 'Suspended.']);
    }

    public function ban(Request $request, User $user, AuditService $audit)
    {
        $user->update(['status' => UserStatus::Banned]);
        $audit->log('admin.user.banned', $request->user(), $user);

        return response()->json(['message' => 'Banned.']);
    }

    public function unban(Request $request, User $user, AuditService $audit)
    {
        $user->update(['status' => UserStatus::Active]);
        $audit->log('admin.user.unbanned', $request->user(), $user);

        return response()->json(['message' => 'Reactivated.']);
    }

    public function verify(Request $request, User $user, AuditService $audit)
    {
        $user->update(['is_verified' => true]);
        $audit->log('admin.user.verified', $request->user(), $user);

        return response()->json(['message' => 'Verified.']);
    }

    public function resetPassword(Request $request, User $user, AuditService $audit)
    {
        $temp = Str::random(12);
        $user->update(['password' => Hash::make($temp)]);
        $audit->log('admin.user.password_reset', $request->user(), $user);

        return response()->json(['temporary_password' => $temp]);
    }

    public function adjustCredits(Request $request, User $user, CreditService $credits, AuditService $audit)
    {
        $request->validate(['amount' => ['required', 'integer', 'min:-1000000', 'max:1000000'], 'description' => ['nullable', 'string', 'max:255']]);
        $amount = (int) $request->input('amount');
        $txn = $amount >= 0
            ? $credits->award($user, $amount, (string) $request->input('description', 'Admin adjustment'))
            : $credits->spend($user, abs($amount), (string) $request->input('description', 'Admin adjustment'));
        $audit->log('admin.user.credits_adjusted', $request->user(), $user, [], ['amount' => $amount]);

        return response()->json($txn, 201);
    }

    public function destroy(Request $request, User $user, AuditService $audit)
    {
        $this->authorize('delete', $user);
        DB::transaction(function () use ($user) {
            $user->delete();
        });
        $audit->log('admin.user.deleted', $request->user(), $user);

        return response()->json(['message' => 'Deleted.']);
    }
}
