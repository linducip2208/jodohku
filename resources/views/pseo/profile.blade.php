@extends('layouts.landing')
@section('title', $user->displayName() . ' — Profil Jodohku')
@section('meta_description', 'Profil publik ' . $user->displayName() . ' di Jodohku, biro jodoh modern Indonesia. Kenalan lebih jauh setelah daftar gratis.')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:680px">
<p class="ld-muted"><a href="/">Beranda</a> / Profil publik</p>
<div class="ld-card" style="display:flex;gap:18px;align-items:center;margin-top:12px">
@if($photo)
<img src="{{ $photo }}" alt="Foto profil {{ $user->displayName() }}" width="120" height="150" loading="lazy" style="border-radius:14px;object-fit:cover">
@else
<div style="width:120px;height:150px;border-radius:14px;background:linear-gradient(135deg,#f43f5e,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:800" aria-hidden="true">{{ strtoupper(substr((string) $user->displayName(), 0, 1)) }}</div>
@endif
<div>
<h1 class="ld-h2" style="margin:0">{{ $user->displayName() }}@if($showAge && $user->age()), {{ $user->age() }}@endif @if($user->is_verified)<span title="Terverifikasi">✓</span>@endif</h1>
<p class="ld-muted">@if($showCity && $user->city){{ $user->city }} · @endif{{ $profile?->occupation ?? 'Member Jodohku' }}</p>
</div>
</div>
@if($showBio && $profile?->bio)
<div class="ld-card" style="margin-top:14px"><h3>Tentang</h3><p class="ld-muted">{{ $profile->bio }}</p></div>
@endif
@if($user->interests->isNotEmpty())
<div class="ld-card" style="margin-top:14px"><h3>Minat</h3><p class="ld-muted">{{ $user->interests->pluck('name')->take(8)->implode(', ') }}</p></div>
@endif
<div class="ld-card" style="margin-top:14px"><p class="ld-muted">Tertarik kenalan dengan {{ $user->displayName() }}? <a href="/register">Daftar gratis</a> dan sapa lewat Jodohku.</p></div>
</div></section>
@endsection
