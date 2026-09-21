@extends('layouts.admin')
@section('title', 'Events')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Events</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Judul</th><th>Kota</th><th>Mulai</th></tr></thead><tbody>
@foreach(\App\Models\Event::latest('id')->take(25)->get() as $e)
<tr><td>{{ $e->id }}</td><td>{{ $e->title }}</td><td>{{ $e->city }}</td><td>{{ $e->starts_at?->format('d M Y') ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
