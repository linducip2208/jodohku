@extends('layouts.admin')
@section('title', 'Comments')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Comments</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Isi</th></tr></thead><tbody>
@foreach(\App\Models\Comment::latest('id')->take(25)->get() as $c)
<tr><td>{{ $c->id }}</td><td>{{ $c->user_id }}</td><td>{{ \Illuminate\Support\Str::limit($c->body ?? '', 70) }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
