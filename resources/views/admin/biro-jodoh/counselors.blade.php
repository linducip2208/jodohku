@extends('layouts.admin')
@section('title', 'Konselor')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
<div class="col"><h2 class="page-title"><i class="ti ti-headset me-1"></i> Konselor</h2></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Tambah / perbarui konselor</h3></div>
<div class="card-body"><form method="POST" action="{{ route('admin.biro-jodoh.counselors') }}">
@csrf
<div class="row g-2">
<div class="col-md-3"><label class="form-label" for="c-user">User ID</label><input class="form-control" id="c-user" name="user_id" required></div>
<div class="col-md-3"><label class="form-label" for="c-spec">Spesialisasi</label><input class="form-control" id="c-spec" name="specialty" required maxlength="120"></div>
<div class="col-md-4"><label class="form-label" for="c-bio">Bio</label><input class="form-control" id="c-bio" name="bio" maxlength="2000"></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="ti ti-plus me-1"></i> Simpan</button></div>
</div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>ID</th><th>User</th><th>Spesialisasi</th><th>Aktif</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($items as $c)
<tr><td>{{ $c->id }}</td><td>{{ $c->user?->displayName() }} ({{ $c->user_id }})</td><td>{{ $c->specialty }}</td>
<td>{!! $c->is_active ? '<span class="badge bg-green">Aktif</span>' : '<span class="badge">Nonaktif</span>' !!}</td>
<td><form method="POST" action="{{ route('admin.biro-jodoh.counselors.update', $c) }}">@csrf @method('PUT')
<input type="hidden" name="is_active" value="{{ $c->is_active ? 0 : 1 }}">
<button class="btn btn-sm" type="submit">{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
@empty<tr><td colspan="5" class="text-secondary">Belum ada data.</td></tr>@endforelse
</tbody></table></div>
<div class="card-footer">{{ $items->links() }}</div></div>
</div></div>
@endsection
