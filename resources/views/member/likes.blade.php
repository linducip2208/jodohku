@extends('layouts.member')
@section('title', 'Likes — Jodohku')
@section('content')
<h1 class="jk-h1">❤️ Likes</h1><p class="jk-muted">Kamu suka &amp; yang menyukaimu (LikeService).</p>
@php
$given = auth()->check() ? auth()->user()->likesGiven()->with('liked')->latest('id')->take(24)->get() : collect();
$quota = null; try { $quota = app(\App\Services\LikeService::class)->likesRemainingToday(auth()->user()); } catch (\Throwable) {}
$whoLiked = $whoLiked ?? null;
@endphp
<div class="jk-section"><div class="jk-h2">Sisa like hari ini: {{ $quota === 'unlimited' ? '♾️ Unlimited (Premium)' : $quota }}</div>
@if(auth()->user()?->isPremium() && $whoLiked)
<div class="jk-grid" style="margin-top:10px">@foreach($whoLiked as $u) @include('components.profile-card', ['user' => $u, 'compact' => true]) @endforeach</div>
@elseif(! auth()->user()?->isPremium())
<p class="jk-muted">Lihat siapa yang menyukaimu dengan <a href="/premium">Premium ⭐</a></p>
@endif
</div>
<div class="jk-section"><div class="jk-h2">Kamu menyukai ({{ $given->count() }})</div>
@if($given->isEmpty()) @include('components.empty', ['icon' => '❤️', 'title' => 'Belum memberi like', 'hint' => 'Jelajahi Discover.']) @else
<div class="jk-grid">@foreach($given as $l) @if($l->liked) @include('components.profile-card', ['user' => $l->liked, 'compact' => true]) @endif @endforeach</div>@endif</div>
@livewire('visitor-list')
@endsection
