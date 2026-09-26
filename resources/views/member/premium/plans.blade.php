@extends('layouts.member')
@section('title', 'Premium — Jodohku')
@section('content')
<h1 class="jk-h1">Premium</h1><p class="jk-muted">Pilih paket yang sesuai dengan perjalananmu.</p>
@php $trialOk = false; try { $trialOk = ! auth()->user()?->isPremium() && app(\App\Services\SubscriptionService::class)->trialEligible(auth()->user()); } catch (\Throwable) {} @endphp
@if($trialOk)
<div class="jk-section" style="border:2px solid var(--brand-primary, #f43f5e)"><div class="jk-h2">🎉 Coba Premium gratis {{ config('jodohku.trial.days', 7) }} hari</div>
<p class="jk-muted">Sekali per akun, tanpa kartu kredit. Otomatis berakhir.</p>
<form method="POST" action="{{ route('member.premium.trial') }}">@csrf<button class="jk-submit" type="submit">Aktifkan trial</button></form></div>
@endif
@if($errors->has('trial'))<div class="jk-alert">{{ $errors->first('trial') }}</div>@endif
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px" aria-label="Fitur premium lainnya">
<a class="jk-pill" href="/gifts">🎁 Gifts</a>
<a class="jk-pill" href="/boosts">🚀 Boost</a>
<a class="jk-pill" href="/credits">💰 Kredit</a>
</div>
@php $plans = collect(); try { $plans = app(\App\Services\MembershipService::class)->plans(); } catch (\Throwable) {} @endphp
<div class="jk-grid">
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
