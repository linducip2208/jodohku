@extends('layouts.admin')
@section('title', 'Gateways')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Payment Gateways</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Kode</th><th>Nama</th><th>Aktif</th></tr></thead><tbody>
@foreach(\App\Models\PaymentGateway::take(20)->get() as $g)
<tr><td>{{ $g->code }}</td><td>{{ $g->name }}</td><td>{{ $g->is_active ? '✅' : '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
