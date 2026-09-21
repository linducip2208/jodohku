@extends('layouts.admin')
@section('title', 'Reports')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Reports</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Pelapor</th><th>Terlapor</th><th>Alasan</th><th>Status</th></tr></thead><tbody>
@foreach(\App\Models\Report::latest('id')->take(25)->get() as $r)
<tr><td>{{ $r->id }}</td><td>{{ $r->reporter_id }}</td><td>{{ $r->reported_user_id }}</td><td>{{ $r->reason }}</td><td>{{ $r->status }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
