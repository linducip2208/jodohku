@extends('layouts.admin')
@section('title', 'Kisah Sukses')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
<div class="col"><h2 class="page-title"><i class="ti ti-heart me-1"></i> Antrean Kisah Sukses</h2></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>ID</th><th>Member</th><th>Pasangan</th><th>Kisah</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($items as $s)
<tr><td>{{ $s->id }}</td><td>{{ $s->user?->displayName() }}</td><td>{{ $s->partner_name }}</td>
<td>{{ \Illuminate\Support\Str::limit($s->story, 120) }}</td>
<td><div class="btn-group">
<form method="POST" action="{{ route('admin.biro-jodoh.stories.moderate', $s) }}">@csrf<input type="hidden" name="action" value="publish"><button class="btn btn-sm btn-success" type="submit">Publish</button></form>
<form method="POST" action="{{ route('admin.biro-jodoh.stories.moderate', $s) }}">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm" type="submit">Tolak</button></form>
</div></td></tr>
@empty<tr><td colspan="5" class="text-secondary">Antrean kosong.</td></tr>@endforelse
</tbody></table></div>
<div class="card-footer">{{ $items->links() }}</div></div>
</div></div>
@endsection
