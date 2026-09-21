@extends('layouts.admin')
@section('title', 'Blocks')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Blocks</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Blocker</th><th>Blocked</th><th>Waktu</th></tr></thead><tbody>
@foreach(\App\Models\Block::latest('id')->take(25)->get() as $b)
<tr><td>{{ $b->id }}</td><td>{{ $b->blocker_id }}</td><td>{{ $b->blocked_id }}</td><td>{{ $b->created_at?->format('d M Y') }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
