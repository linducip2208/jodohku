@extends('layouts.admin')
@section('title', 'Ads')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Ads (AdService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Judul</th><th>Status</th><th>Impresi</th></tr></thead><tbody>
@foreach(\App\Models\Ad::take(20)->get() as $a)
<tr><td>{{ $a->id }}</td><td>{{ $a->title ?? $a->name ?? $a->id }}</td><td>{{ $a->status }}</td><td>{{ $a->impressions_count ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
