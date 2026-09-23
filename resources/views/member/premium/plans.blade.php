@extends('layouts.member')
@section('title', 'Premium — Jodohku')
@section('content')
<h1 class="jk-h1">Premium</h1><p class="jk-muted">Didukung MembershipService + SubscriptionService + PaymentService.</p>
@php $plans = collect(); try { $plans = app(\App\Services\MembershipService::class)->plans(); } catch (\Throwable) {} @endphp
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($plans as $p)
<div class="jk-section"><div class="jk-h2">{{ $p->name ?? $p->code }} @if(auth()->user()?->isPremium())<span class="jk-pill premium">AKTIF</span>@endif</div>
<div class="jk-muted">Rp{{ number_format($p->price ?? 0, 0, ',', '.') }} / {{ $p->interval ?? 'bulan' }}</div>
<ul class="jk-muted">@foreach((array)($p->features ?? ['Like tanpa batas','Lihat yang like','Filter lanjutan','Rewind','Incognito']) as $f)<li>{{ is_string($f) ? $f : json_encode($f) }}</li>@endforeach</ul>
@if(auth()->check())<form method="POST" action="/premium/checkout">@csrf<input type="hidden" name="plan" value="{{ $p->code ?? $p->id }}"><button class="jk-submit" type="submit">Pilih Paket →</button></form>@endif
</div>
@empty
@include('components.empty', ['icon' => 'bintang', 'title' => 'Paket belum tersedia', 'hint' => 'Hubungi admin untuk mengaktifkan paket membership.'])
@endforelse
</div>
@endsection
