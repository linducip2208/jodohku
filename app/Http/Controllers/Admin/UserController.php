<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserUpdateRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return $request->wantsJson() ? response()->json($users) : view('admin.users', ['users' => $users]);
    }

    public function show(Request $request, User $user)
    {
        $user->load(['profile', 'photos', 'subscriptions.plan', 'payments', 'creditWallet', 'verificationRequests']);

        return $request->wantsJson() ? response()->json($user) : view('admin.users', ['users' => collect([$user])]);
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

    public function photoQueue(Request $request)
    {
        $photos = \App\Models\ProfilePhoto::pendingReview()->with('user')->paginate(25);

        return $request->wantsJson()
            ? response()->json($photos)
            : view('admin.photos', ['photos' => $photos]);
    }

    public function moderatePhoto(Request $request, int $photo, \App\Services\PhotoService $photos)
    {
        $record = \App\Models\ProfilePhoto::findOrFail($photo);
        $request->validate(['action' => ['required', 'string', 'in:approve,reject'], 'reason' => ['nullable', 'string', 'max:500']]);
        $action = $request->string('action')->toString();
        $result = $action === 'approve'
            ? $photos->approve($record, $request->user())
            : $photos->reject($record, $request->user(), (string) $request->input('reason', ''));

        return $request->wantsJson()
            ? response()->json($result)
            : back()->with('status', 'Photo '.$action.'d.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAdminOverview', \App\Models\User::class);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-'.now()->format('Y-m-d').'.csv"',
        ];
        $columns = ['id', 'name', 'email', 'phone', 'display_name', 'gender', 'date_of_birth', 'city', 'status', 'role', 'is_verified', 'is_premium', 'is_online', 'created_at', 'last_active_at'];
        $callback = function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            \App\Models\User::query()->with(['profile'])->chunkById(500, function ($users) use ($handle) {
                foreach ($users as $u) {
                    fputcsv($handle, [
                        $u->id, $u->name, $u->email, $u->phone, $u->display_name, $u->gender, $u->date_of_birth, $u->city, $u->status, $u->role, $u->is_verified, $u->is_premium, $u->is_online, $u->created_at, $u->last_active_at,
                    ]);
                }
            });
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function impersonate(Request $request, User $user)
    {
        $request->authorize('impersonate', $user);
        if (! $request->user()->isAdmin()) {
            abort(403, 'Admins only.');
        }
        session()->put('impersonating', $user->id);
        Auth::login($user);

        return response()->json(['message' => 'Now impersonating user #'.$user->id, 'user_id' => $user->id]);
    }

    public function stopImpersonate(Request $request)
    {
        if (! session()->has('impersonating')) {
            abort(403, 'Not impersonating.');
        }
        $originalId = session()->pull('impersonating');
        Auth::login(\App\Models\User::findOrFail($originalId));

        return response()->json(['message' => 'Stopped impersonating.']);
    }

    public function bulkAction(Request $request, AuditService $audit)
    {
        $request->validate([
            'user_ids' => ['required', 'array', 'max:500', 'exists:users,id'],
            'action' => ['required', 'string', 'in:suspend,unsuspend,ban,unban,verify,unverify'],
        ]);
        $userIds = (array) $request->input('user_ids');
        $action = $request->string('action');
        $users = User::whereIn('id', $userIds)->get();
        $results = [];

        foreach ($users as $user) {
            match ($action) {
                'suspend' => $user->update(['status' => UserStatus::Suspended]),
                'unsuspend' => $user->update(['status' => UserStatus::Active]),
                'ban' => $user->update(['status' => UserStatus::Banned]),
                'unban' => $user->update(['status' => UserStatus::Active]),
                'verify' => $user->update(['is_verified' => true]),
                'unverify' => $user->update(['is_verified' => false]),
            };
            $audit->log('admin.user.bulk_'.$action, $request->user(), $user);
            $results[] = ['user_id' => $user->id, 'status' => 'processed'];
        }

        return response()->json(['action' => $action, 'processed' => count($results), 'results' => $results]);
    }
}
