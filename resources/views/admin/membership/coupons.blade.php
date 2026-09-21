@extends('layouts.admin')
@section('title', 'Coupons')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Coupons</h3></div>
<div class="card-body">
@php $coupons = collect(); try { $coupons = \App\Models\Coupon::withCount('redemptions')->latest('id')->take(50)->get(); } catch (\Throwable) {} @endphp
<form method="POST" action="{{ route('admin.coupons.store') }}" class="row g-2 mb-3">
@csrf
<div class="col-md-2"><input name="code" class="form-control" placeholder="KODE" required></div>
<div class="col-md-3"><input name="name" class="form-control" placeholder="Nama promo" required></div>
<div class="col-md-2"><select name="type" class="form-select"><option value="percent">Persen %</option><option value="fixed">Nominal Rp</option></select></div>
<div class="col-md-2"><input name="value" type="number" step="0.01" min="0" class="form-control" placeholder="Nilai" required></div>
<div class="col-md-2"><input name="min_order" type="number" step="0.01" min="0" class="form-control" placeholder="Min. order"></div>
<div class="col-md-1"><button class="btn btn-primary">Buat</button></div>
</form>
<div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>Kode</th><th>Tipe</th><th>Nilai</th><th>Terpakai</th><th>Aktif</th></tr></thead>
<tbody>
@forelse($coupons as $c)
<tr><td><strong>{{ $c->code }}</strong><div class="text-secondary">{{ $c->name }}</div></td><td>{{ $c->type }}</td><td>{{ $c->type === 'percent' ? $c->value.'%' : 'Rp '.number_format($c->value) }}</td><td>{{ $c->used_count }}{{ $c->usage_limit ? '/'.$c->usage_limit : '' }} ({{ $c->redemptions_count }})</td><td>{{ $c->is_active ? 'Ya' : 'Tidak' }}</td></tr>
@empty
<tr><td colspan="5" class="text-secondary">Belum ada kupon.</td></tr>
@endforelse
</tbody></table></div>
<p class="text-secondary">Kupon otomatis tampil di checkout premium/kredit via field <code>coupon_code</code>. Validasi &amp; redeem tercatat di audit log.</p>
</div></div>
@endsection
