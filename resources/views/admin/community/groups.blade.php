@extends('layouts.admin')
@section('title', 'Groups')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Groups</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Nama</th><th>Members</th></tr></thead><tbody>
@foreach(\App\Models\Group::withCount('members')->take(20)->get() as $g)
<tr><td>{{ $g->id }}</td><td>{{ $g->name }}</td><td>{{ $g->members_count }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
