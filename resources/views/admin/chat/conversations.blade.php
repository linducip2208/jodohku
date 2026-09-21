@extends('layouts.admin')
@section('title', 'Conversations')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Conversations (ChatService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Tipe</th><th>Members</th><th>Pesan terakhir</th></tr></thead><tbody>
@foreach(\App\Models\Conversation::withCount('messages')->latest('id')->take(25)->get() as $c)
<tr><td>{{ $c->id }}</td><td>{{ $c->type?->value ?? $c->type }}</td><td>{{ $c->messages_count }}</td><td>{{ $c->last_message_at?->diffForHumans() ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
