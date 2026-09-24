@extends('layouts.member')
@section('title', 'Dompet Kredit — Jodohku')
@section('content')
<h1 class="jk-h1">Dompet Kredit</h1>
@php $u = auth()->user(); $bal = 0; $tx = collect(); $products = collect(); try { if ($u) { $bal = app(\App\Services\CreditService::class)->balance($u); $tx = $u->creditTransactions()->latest('id')->take(20)->get(); $products = \App\Models\CreditProduct::where('is_active', true)->take(6)->get(); } } catch (\Throwable) {} @endphp
<div class="jk-hero"><div class="jk-h2">Saldo: {{ $bal }} kredit</div><p class="jk-muted">Gunakan untuk superlike, boost, gift.</p></div>
@if($products->count())<div class="jk-section"><div class="jk-h2">Beli Kredit</div>@foreach($products as $pr)<form method="POST" action="/credits/checkout" style="display:flex;gap:8px;margin-bottom:8px">@csrf<input type="hidden" name="product" value="{{ $pr->code ?? $pr->id }}"><span style="flex:1">{{ $pr->name }} — {{ $pr->credits }} kredit (Rp{{ number_format($pr->price ?? 0,0,',','.') }})</span><button class="jk-btn jk-btn-like" type="submit">Beli</button></form>@endforeach</div>@endif
<div class="jk-section"><div class="jk-h2">Riwayat</div><table class="jk-table"><thead><tr><th>Waktu</th><th>Jumlah</th><th>Keterangan</th></tr></thead><tbody>@forelse($tx as $t)<tr><td>{{ $t->created_at?->format('d M H:i') }}</td><td>{{ $t->amount }}</td><td>{{ $t->description }}</td></tr>@empty<tr><td colspan="3" class="jk-muted">Belum ada transaksi.</td></tr>@endforelse</tbody></table></div>
@endsection
