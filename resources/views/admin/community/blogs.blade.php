@extends('layouts.admin')
@section('title', 'Blogs')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Blog Posts</h3></div>
<div class="card-body">
@php $posts = collect(); try { $posts = \App\Models\BlogPost::with('author')->latest('id')->take(50)->get(); } catch (\Throwable) {} @endphp
<form method="POST" action="{{ route('admin.community.blogs') }}" class="row g-2 mb-3">
@csrf
<div class="col-md-4"><input name="title" class="form-control" placeholder="Judul" required></div>
<div class="col-md-3"><input name="excerpt" class="form-control" placeholder="Ringkasan"></div>
<div class="col-md-2"><select name="status" class="form-select"><option value="draft">Draft</option><option value="published">Published</option></select></div>
<div class="col-md-3"><button class="btn btn-primary">Terbitkan</button></div>
<div class="col-12"><textarea name="body" class="form-control" rows="2" placeholder="Isi artikel" required></textarea></div>
</form>
<div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>Judul</th><th>Status</th><th>Views</th><th>Terbit</th></tr></thead>
<tbody>
@forelse($posts as $p)
<tr><td>{{ $p->title }}<div class="text-secondary">/{{ $p->slug }}</div></td><td><span class="badge bg-{{ $p->status === 'published' ? 'green' : 'yellow' }}">{{ $p->status }}</span></td><td>{{ number_format($p->view_count) }}</td><td>{{ $p->published_at?->format('d M Y') ?? '—' }}</td></tr>
@empty
<tr><td colspan="4" class="text-secondary">Belum ada artikel.</td></tr>
@endforelse
</tbody></table></div>
</div></div>
@endsection
