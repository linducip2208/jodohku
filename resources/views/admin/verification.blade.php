@extends('layouts.admin')
@section('title', 'Verification')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Verification Requests (VerificationService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Tipe</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@foreach(\App\Models\VerificationRequest::latest('id')->take(25)->get() as $r)
<tr><td>{{ $r->id }}</td><td>{{ $r->user_id }}</td><td>{{ $r->type }}</td><td><span class="badge bg-yellow-lt">{{ $r->status }}</span></td>
<td><form method="POST" action="/admin/verification/{{ $r->id }}/approve" style="display:inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
<form method="POST" action="/admin/verification/{{ $r->id }}/reject" style="display:inline">@csrf<button class="btn btn-sm btn-danger">Reject</button></form></td></tr>
@endforeach
</tbody></table></div></div>
@endsection
