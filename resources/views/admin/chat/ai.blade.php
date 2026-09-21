@extends('layouts.admin')
@section('title', 'AI Chat')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">AI Usage (AiService + AiChatAssistantService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Tujuan</th><th>Token</th><th>Biaya</th></tr></thead><tbody>
@foreach(\App\Models\AiUsageLog::latest('id')->take(25)->get() as $l)
<tr><td>{{ $l->id }}</td><td>{{ $l->user_id ?? '—' }}</td><td>{{ $l->purpose ?? '—' }}</td><td>{{ $l->tokens ?? $l->total_tokens ?? '—' }}</td><td>{{ $l->cost ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
