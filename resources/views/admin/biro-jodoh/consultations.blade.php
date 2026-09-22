@extends('layouts.admin')
@section('title', 'Konsultasi')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
<div class="col"><h2 class="page-title"><i class="ti ti-calendar-event me-1"></i> Konsultasi</h2></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card"><div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>ID</th><th>Member</th><th>Konselor</th><th>Topik</th><th>Jadwal</th><th>Status</th><th>Laporan</th></tr></thead>
<tbody>
@forelse($items as $c)
<tr><td>{{ $c->id }}</td><td>{{ $c->user?->displayName() }}</td><td>{{ $c->counselor?->user?->displayName() }}</td>
<td>{{ \Illuminate\Support\Str::limit($c->topic, 40) }}</td><td>{{ $c->scheduled_at?->format('d M Y H:i') }}</td>
<td><span class="badge bg-blue">{{ $c->status->label() }}</span></td>
<td>{{ $c->share_report ? 'Dibagikan' : '—' }}</td></tr>
@empty<tr><td colspan="7" class="text-secondary">Belum ada data.</td></tr>@endforelse
</tbody></table></div>
<div class="card-footer">{{ $items->links() }}</div></div>
</div></div>
@endsection
