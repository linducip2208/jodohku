@extends('layouts.admin')
@section('title', 'Posts')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Posts</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Isi</th></tr></thead><tbody>
@foreach(\App\Models\Post::latest('id')->take(25)->get() as $p)
<tr><td>{{ $p->id }}</td><td>{{ $p->user_id }}</td><td>{{ \Illuminate\Support\Str::limit($p->body ?? $p->content ?? '', 70) }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
