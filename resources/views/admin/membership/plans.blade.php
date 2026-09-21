@extends('layouts.admin')
@section('title', 'Plans')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Membership Plans (MembershipService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Kode</th><th>Nama</th><th>Harga</th><th>Aktif</th></tr></thead><tbody>
@foreach(\App\Models\MembershipPlan::take(20)->get() as $p)
<tr><td>{{ $p->code }}</td><td>{{ $p->name }}</td><td>Rp{{ number_format($p->price ?? 0,0,',','.') }}</td><td>{!! $p->is_active ? '<span class="badge bg-green"><i class="ti ti-check"></i></span>' : '<span class="badge">—</span>' !!}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
