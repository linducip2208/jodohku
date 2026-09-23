@extends('layouts.member')
@section('title', 'Riwayat Pembayaran — Jodohku')
@section('content')
<h1 class="jk-h1">Pembayaran</h1><p class="jk-muted">Status resmi selalu mengikuti webhook gateway.</p>
@php $payments = $payments ?? collect(); @endphp
<div class="jk-section"><table class="jk-table"><thead><tr><th>Invoice</th><th>Total</th><th>Status</th></tr></thead><tbody>
@forelse($payments as $p)
<tr><td>{{ $p->invoice_number ?? $p->id }}</td><td>Rp{{ number_format($p->total_amount ?? $p->amount ?? 0, 0, ',', '.') }}</td><td><span class="jk-pill {{ ($p->status?->value ?? $p->status) === 'paid' ? 'verified' : '' }}">{{ $p->status?->value ?? $p->status }}</span></td></tr>
@empty
<tr><td colspan="3">@include('components.empty', ['icon' => 'riwayat', 'title' => 'Belum ada pembayaran', 'hint' => 'Upgrade Premium atau beli kredit.'])</td></tr>
@endforelse
</tbody></table></div>
@endsection
