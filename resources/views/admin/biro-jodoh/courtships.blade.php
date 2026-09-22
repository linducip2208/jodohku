@extends('layouts.admin')
@section('title', 'Taaruf')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
<div class="col"><h2 class="page-title"><i class="ti ti-heart-handshake me-1"></i> Perjalanan Taaruf</h2></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card"><div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>ID</th><th>Inisiator</th><th>Pasangan</th><th>Tahap</th><th>Status</th><th>Wali</th><th>Mulai</th></tr></thead>
<tbody>
@forelse($items as $c)
<tr><td>{{ $c->id }}</td><td>{{ $c->initiator?->displayName() }}</td><td>{{ $c->partner?->displayName() }}</td>
<td><span class="badge bg-blue">{{ $c->stage->label() }}</span></td><td>{{ $c->status->label() }}</td>
<td>{{ $c->guardian_name ?? '—' }}@if($c->guardian_approved_at) <i class="ti ti-check text-green"></i>@endif</td>
<td>{{ $c->started_at?->format('d M Y') }}</td></tr>
@empty<tr><td colspan="7" class="text-secondary">Belum ada data.</td></tr>@endforelse
</tbody></table></div>
<div class="card-footer">{{ $items->links() }}</div></div>
</div></div>
@endsection
