@extends('layouts.admin')
@section('title', 'Boosts')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Boosts (BoostService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Status</th><th>Berakhir</th></tr></thead><tbody>
@foreach(\App\Models\Boost::latest('id')->take(25)->get() as $b)
<tr><td>{{ $b->id }}</td><td>{{ $b->user_id }}</td><td>{{ $b->status }}</td><td>{{ $b->expires_at?->format('d M H:i') ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
