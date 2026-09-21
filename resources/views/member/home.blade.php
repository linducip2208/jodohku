@extends('layouts.member')
@section('title', 'Home — Jodohku')
@section('content')
@livewire('profile-completeness')
<div class="jk-hero">
<h1 class="jk-h1">Hai {{ auth()->user()?->displayName() ?? 'kamu' }} 👋</h1>
<p class="jk-muted">Rekomendasi teratas hari ini berdasarkan skor kompatibilitasmu.</p>
<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center" href="/discover">🔍 Discover</a>
<a class="jk-btn jk-btn-super" style="text-decoration:none;text-align:center" href="/boosts">🚀 Boost</a>
<a class="jk-btn jk-btn-fav" style="text-decoration:none;text-align:center" href="/premium">⭐ Premium</a>
</div>
</div>
<div class="jk-section"><div class="jk-h2">💘 Daily Picks untukmu</div>
@php
$picks = collect();
try {
  $u = auth()->user();
  if ($u) { $picks = app(\App\Services\DiscoveryService::class)->discover($u, ['sort' => 'compatibility'], 4)->collect(); }
} catch (\Throwable) {}
@endphp
@if($picks->isEmpty())
@include('components.empty', ['icon' => '💘', 'title' => auth()->check() ? 'Lengkapi profil untuk daily picks' : 'Masuk untuk melihat daily picks', 'hint' => 'Tambah foto + jawab kuesioner.'])
@else
<div class="jk-grid">@foreach($picks as $p) @include('components.profile-card', ['user' => $p, 'score' => $p->compatibility_score ?? null]) @endforeach</div>
@endif
</div>
<div class="jk-grid" style="grid-template-columns:1fr 1fr">
<div class="jk-section">💬 <strong>Chat</strong><div class="jk-muted">Lanjut ngobrol dengan match.</div><a href="/chat">Buka inbox →</a></div>
<div class="jk-section">🎉 <strong>Events</strong><div class="jk-muted">Meetup akhir pekan.</div><a href="/events">Lihat event →</a></div>
</div>
@endsection
