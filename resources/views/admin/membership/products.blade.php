@extends('layouts.admin')
@section('title', 'Products')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Credit Products</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Kode</th><th>Nama</th><th>Kredit</th><th>Harga</th></tr></thead><tbody>
@foreach(\App\Models\CreditProduct::take(20)->get() as $p)
<tr><td>{{ $p->code ?? $p->id }}</td><td>{{ $p->name }}</td><td>{{ $p->credits ?? '—' }}</td><td>Rp{{ number_format($p->price ?? 0,0,',','.') }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
