@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Users ({{ \App\Models\User::count() }})</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Tipe</th><th>Status</th><th>Premium</th><th>Aksi</th></tr></thead><tbody>
@foreach(\App\Models\User::latest('id')->take(25)->get() as $u)
<tr><td>{{ $u->id }}</td><td>{{ $u->displayName() }} @if($u->is_verified)<i class="ti ti-badge-check text-blue" title="Verified"></i>@endif</td><td>{{ $u->email }}</td><td>{{ $u->account_type?->value ?? $u->account_type }}</td><td>{{ $u->status?->value ?? $u->status }}</td><td>{!! $u->is_premium ? '<span class="badge bg-yellow"><i class="ti ti-star"></i> Premium</span>' : '<span class="badge">Free</span>' !!}</td>
<td><a class="btn btn-sm" href="/profile/{{ $u->id }}">Lihat</a></td></tr>
@endforeach
</tbody></table></div></div>
@endsection
