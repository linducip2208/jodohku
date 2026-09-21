@extends('layouts.admin')
@section('title', 'Messages')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Messages (MessageModerationService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Conv</th><th>Sender</th><th>Isi</th><th>Status</th></tr></thead><tbody>
@foreach(\App\Models\Message::latest('id')->take(25)->get() as $m)
<tr><td>{{ $m->id }}</td><td>{{ $m->conversation_id }}</td><td>{{ $m->sender_id }}</td><td>{{ \Illuminate\Support\Str::limit($m->body ?? '', 60) }}</td><td>{{ $m->status?->value ?? $m->status }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
