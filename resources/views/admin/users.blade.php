@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<div class="card">
<div class="card-header">
<div class="row g-2 align-items-center">
<div class="col"><h3 class="card-title mb-0">Users ({{ $users->total() }})</h3></div>
<div class="col-auto">
<form method="get" class="d-flex flex-wrap gap-2">
<input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari nama/email/username..." style="min-width:220px">
<select name="account_type" class="form-select form-select-sm">
<option value="">Semua tipe</option>
@foreach (\App\Enums\AccountType::cases() as $t)
<option value="{{ $t->value }}" {{ request('account_type') === $t->value ? 'selected' : '' }}>{{ $t->label() }}</option>
@endforeach
</select>
<select name="status" class="form-select form-select-sm">
<option value="">Semua status</option>
@foreach (\App\Enums\UserStatus::cases() as $s)
<option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>{{ $s->label() }}</option>
@endforeach
</select>
<select name="demo" class="form-select form-select-sm" aria-label="Filter demo">
<option value="">Semua + demo</option>
<option value="1" {{ request('demo') === '1' ? 'selected' : '' }}>Demo saja</option>
<option value="0" {{ request('demo') === '0' ? 'selected' : '' }}>Bukan demo</option>
</select>
<button class="btn btn-primary btn-sm">Terapkan</button>
@if (request()->query())<a class="btn btn-ghost-secondary btn-sm" href="{{ route('admin.users.index') }}">Reset</a>@endif
</form>
</div>
</div>
</div>
<div class="table-responsive">
<table class="table table-vcenter table-row-dotted align-middle mb-0">
<thead class="table-light">
<tr>
<th>ID</th><th>Nama</th><th>Email</th><th>Tipe</th><th>Status</th><th>Premium</th><th class="text-end">Aksi</th>
</tr>
</thead>
<tbody>
@forelse ($users as $u)
<tr>
<td>{{ $u->id }}</td>
<td>{{ $u->displayName() }} @if ($u->is_verified)<i class="ti ti-badge-check text-blue" title="Terverifikasi"></i>@endif @if ($u->is_demo)<span class="badge bg-purple-lt" title="Akun demo">Demo</span>@endif</td>
<td>{{ $u->email ?: '—' }}</td>
<td>{{ $u->account_type?->label() ?? $u->account_type }}</td>
<td>{{ $u->status?->label() ?? $u->status }}</td>
<td>{!! $u->is_premium ? '<span class="badge bg-yellow"><i class="ti ti-star"></i> Premium</span>' : '<span class="badge">Free</span>' !!}</td>
<td class="text-end">
<div class="btn-group dropend">
<button type="button" class="btn btn-ghost-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Aksi</button>
<div class="dropdown-menu">
<a class="dropdown-item" href="/profile/{{ $u->id }}">Lihat</a>
@can('moderator')
@if ($u->status === \App\Enums\UserStatus::Suspended)
<form method="post" action="{{ route('admin.users.suspend', $u) }}" class="d-inline">@csrf<button class="dropdown-item">Aktifkan</button></form>
@elseif ($u->status === \App\Enums\UserStatus::Banned)
<form method="post" action="{{ route('admin.users.ban', $u) }}" class="d-inline">@csrf<button class="dropdown-item">Buka Blokir</button></form>
@else
<form method="post" action="{{ route('admin.users.suspend', $u) }}" class="d-inline">@csrf<button class="dropdown-item">Suspend</button></form>
@endif
@endcan
@can('admin')
@if ($u->is_verified)
<form method="post" action="{{ route('admin.users.verify', $u) }}" class="d-inline">@csrf<button class="dropdown-item">Batalkan verif</button></form>
@else
<form method="post" action="{{ route('admin.users.verify', $u) }}" class="d-inline">@csrf<button class="dropdown-item">Verifikasi</button></form>
@endif
<form method="post" action="{{ route('admin.users.ban', $u) }}" class="d-inline">@csrf<button class="dropdown-item text-danger">Ban</button></form>
@endcan
</div>
</div>
</td>
</tr>
@empty
<tr><td colspan="7" class="text-center text-secondary">Tidak ada pengguna.</td></tr>
@endforelse
</tbody>
</table>
</div>
@if ($users->total() > $users->count())
<div class="card-footer">{{ $users->appends(request()->input())->links() }}</div>
@endif
</div>
@endsection
