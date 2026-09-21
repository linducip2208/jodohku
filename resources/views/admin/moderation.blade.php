@extends('layouts.admin')
@section('title', 'Moderation')
@section('content')
@livewire('admin-moderation-queue')
<div class="card mt-3"><div class="card-header"><h3 class="card-title">Moderation Rules</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Nama</th><th>Aksi</th><th>Aktif</th></tr></thead><tbody>
@foreach(\App\Models\ModerationRule::take(20)->get() as $r)
<tr><td>{{ $r->id }}</td><td>{{ $r->name ?? $r->code ?? $r->id }}</td><td>{{ $r->action ?? '—' }}</td><td>{!! $r->is_active ? '<span class="badge bg-green"><i class="ti ti-check"></i></span>' : '<span class="badge">—</span>' !!}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
