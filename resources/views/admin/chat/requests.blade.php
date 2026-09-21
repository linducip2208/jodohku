@extends('layouts.admin')
@section('title', 'Chat Requests')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Chat Requests</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Dari</th><th>Ke</th><th>Status</th></tr></thead><tbody>
@foreach(\App\Models\ChatRequest::latest('id')->take(25)->get() as $r)
<tr><td>{{ $r->id }}</td><td>{{ $r->sender_id ?? $r->from_user_id ?? '—' }}</td><td>{{ $r->receiver_id ?? $r->to_user_id ?? '—' }}</td><td>{{ $r->status }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
