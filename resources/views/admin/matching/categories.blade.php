@extends('layouts.admin')
@section('title', 'Categories')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Question Categories</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Nama</th><th>Deskripsi</th></tr></thead><tbody>
@foreach(\App\Models\QuestionCategory::take(25)->get() as $c)
<tr><td>{{ $c->id }}</td><td>{{ $c->name }}</td><td>{{ \Illuminate\Support\Str::limit($c->description ?? '', 60) }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
