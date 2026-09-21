@extends('layouts.admin')
@section('title', 'Audit Logs')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Audit Logs (AuditService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Aksi</th><th>Aktor</th><th>Waktu</th></tr></thead><tbody>
@foreach(\App\Models\AuditLog::latest('id')->take(30)->get() as $a)
<tr><td>{{ $a->id }}</td><td>{{ $a->action }}</td><td>{{ $a->actor_id ?? '—' }}</td><td>{{ $a->created_at?->format('d M H:i') }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
