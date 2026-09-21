@extends('layouts.admin')
@section('title', 'AI Usage')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">AI Usage &amp; Cost</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Provider</th><th>Tujuan</th><th>Token</th><th>Biaya</th><th>Waktu</th></tr></thead><tbody>
@foreach(\App\Models\AiUsageLog::latest('id')->take(30)->get() as $l)
<tr><td>{{ $l->id }}</td><td>{{ $l->provider ?? '—' }}</td><td>{{ $l->purpose ?? '—' }}</td><td>{{ $l->tokens ?? $l->total_tokens ?? '—' }}</td><td>{{ $l->cost ?? '—' }}</td><td>{{ $l->created_at?->format('d M H:i') }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
