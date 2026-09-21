@extends('layouts.admin')
@section('title', 'Photos')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Photos</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Path</th><th>Utama</th><th>Status</th></tr></thead><tbody>
@foreach(\App\Models\ProfilePhoto::latest('id')->take(25)->get() as $p)
<tr><td>{{ $p->id }}</td><td>{{ $p->user_id }}</td><td>{{ \Illuminate\Support\Str::limit($p->path ?? $p->url ?? '', 50) }}</td><td>{{ $p->is_primary ? '⭐' : '—' }}</td><td>{{ $p->status ?? 'ok' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
