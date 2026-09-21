@extends('layouts.admin')
@section('title', 'Forums')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Forums</h3></div>
<div class="card-body">
@php $forums = collect(); try { $forums = \App\Models\Forum::withCount('threads')->orderBy('sort_order')->get(); } catch (\Throwable) {} @endphp
<form method="POST" action="{{ route('admin.community.forums') }}" class="row g-2 mb-3">
@csrf
<div class="col-md-4"><input name="name" class="form-control" placeholder="Nama forum" required></div>
<div class="col-md-3"><input name="slug" class="form-control" placeholder="slug-unik" required></div>
<div class="col-md-3"><input name="description" class="form-control" placeholder="Deskripsi"></div>
<div class="col-md-2"><button class="btn btn-primary">Buat</button></div>
</form>
<div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>Forum</th><th>Threads</th><th>Aktif</th></tr></thead>
<tbody>
@forelse($forums as $f)
<tr><td>{{ $f->name }}<div class="text-secondary">/{{ $f->slug }} — {{ $f->description }}</div></td><td>{{ $f->threads_count }}</td><td>{{ $f->is_active ? 'Ya' : 'Tidak' }}</td></tr>
@empty
<tr><td colspan="3" class="text-secondary">Belum ada forum.</td></tr>
@endforelse
</tbody></table></div>
<p class="text-secondary">Moderasi thread/balasan via endpoint JSON admin (hide, lock, pin, delete).</p>
</div></div>
@endsection
