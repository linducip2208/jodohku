@extends('layouts.admin')
@section('title', 'Credits')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Credit Transactions (CreditService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Jumlah</th><th>Tipe</th><th>Waktu</th></tr></thead><tbody>
@foreach(\App\Models\CreditTransaction::latest('id')->take(25)->get() as $t)
<tr><td>{{ $t->id }}</td><td>{{ $t->user_id }}</td><td>{{ $t->amount }}</td><td>{{ $t->type ?? '—' }}</td><td>{{ $t->created_at?->format('d M H:i') }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
