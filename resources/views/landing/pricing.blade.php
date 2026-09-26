@extends('layouts.landing')
@section('content')
<section class="ld-section"><div class="ld-wrap">
<h1 class="ld-h2">Harga {{ $brandTheme['name'] ?? 'Jodohku' }}</h1>
<p class="ld-muted">Mulai gratis. Upgrade kapan saja. Batalkan kapan saja.</p>
@auth
@php $trialOk = false; try { $trialOk = ! auth()->user()->isPremium() && app(\App\Services\SubscriptionService::class)->trialEligible(auth()->user()); } catch (\Throwable) {} @endphp
@if($trialOk)<div class="ld-card" style="border:2px solid var(--brand-primary);margin-top:16px"><h3>🎉 Trial {{ config('jodohku.trial.days', 7) }} hari gratis</h3><p class="ld-muted">Sekali per akun.</p><form method="POST" action="{{ route('member.premium.trial') }}">@csrf<button class="ld-btn" type="submit">Aktifkan trial</button></form></div>@endif
@endauth
@php $plans = collect(); try { $plans = app(\App\Services\MembershipService::class)->plans(); } catch (\Throwable) {} @endphp
<div class="ld-grid3" style="margin-top:20px">
<div class="ld-card"><h3>Gratis</h3><div class="ld-price">Rp0</div><p class="ld-muted">{{ config('jodohku.limits.free_daily_likes', 20) }} like/hari · chat dengan match · event publik</p><a class="ld-btn ghost" href="{{ route('register') }}">Daftar</a></div>
@forelse($plans as $p)
<div class="ld-card" @if($loop->first) style="border:2px solid var(--brand-primary)" @endif><h3>{{ $p->name }}</h3><div class="ld-price">Rp{{ number_format((float) $p->price, 0, ',', '.') }}<span style="font-size:14px;color:#71717a">/{{ $p->interval }}</span></div>
<p class="ld-muted">{{ \Illuminate\Support\Str::limit($p->description ?? 'Semua fitur premium.', 120) }}</p>
@auth<a class="ld-btn" href="/premium">Pilih Paket</a>@else<a class="ld-btn" href="{{ route('register') }}">Daftar untuk Paket Ini</a>@endauth</div>
@empty
<div class="ld-card"><p class="ld-muted">Paket belum tersedia.</p></div>
@endforelse
</div>
<p class="ld-muted" style="margin-top:16px">Pembayaran aman via gateway Indonesia · <a href="/terms">Syarat</a> · <a href="/privacy">Privasi</a></p>
</div></section>
@endsection
