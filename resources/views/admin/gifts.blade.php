@extends('layouts.admin')
@section('title', 'Gifts')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Gifts (GiftService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Kode</th><th>Nama</th><th>Kredit</th></tr></thead><tbody>
@foreach(\App\Models\Gift::take(20)->get() as $g)
<tr><td>{{ $g->code }}</td><td>{{ $g->name }}</td><td>{{ $g->credits ?? $g->price_credits ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
