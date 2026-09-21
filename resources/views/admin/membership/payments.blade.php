@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Payments (PaymentService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Jumlah</th><th>Status</th><th>Gateway</th></tr></thead><tbody>
@foreach(\App\Models\Payment::latest('id')->take(25)->get() as $p)
<tr><td>{{ $p->id }}</td><td>{{ $p->user_id }}</td><td>Rp{{ number_format($p->amount ?? 0,0,',','.') }}</td><td>{{ $p->status }}</td><td>{{ $p->gateway ?? $p->gateway_code ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
