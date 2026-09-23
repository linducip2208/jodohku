@extends('layouts.member')
@section('title', 'Likes — Jodohku')
@section('content')
@php
$tab = request('tab', 'received');
$tab = in_array($tab, ['received', 'given', 'super', 'favorites'], true) ? $tab : 'received';
$me = auth()->user();
$isPremium = (bool) $me?->isPremium();
$quota = null;
try { $quota = app(\App\Services\LikeService::class)->likesRemainingToday($me); } catch (\Throwable) {}
$received = collect(); $given = collect(); $supers = collect(); $favs = collect();
try {
    if ($me) {
        $given = $me->likesGiven()->with(['liked.profile', 'liked.interests'])->latest('id')->take(24)->get();
        $supers = \App\Models\SuperLike::where('sender_id', $me->id)->with(['receiver.profile'])->latest('id')->take(24)->get();
        $favs = \App\Models\Favorite::where('user_id', $me->id)->with(['favorited.profile'])->latest('id')->take(24)->get();
        if ($isPremium) {
            $received = \App\Models\Like::where('liked_id', $me->id)->with(['liker.profile', 'liker.interests'])->latest('id')->take(24)->get();
        }
    }
} catch (\Throwable) {}
@endphp
<h1 class="jk-h1">Likes</h1>
<p class="jk-muted">Sisa like hari ini: <strong>{{ $quota === 'unlimited' ? 'Unlimited (Premium)' : $quota }}</strong></p>
<div class="jk-tabs" role="tablist" aria-label="Kategori likes">
<a class="jk-tab {{ $tab === 'received' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === 'received' ? 'true' : 'false' }}" href="/likes?tab=received">Menyukaimu</a>
<a class="jk-tab {{ $tab === 'given' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === 'given' ? 'true' : 'false' }}" href="/likes?tab=given">Kamu suka</a>
<a class="jk-tab {{ $tab === 'super' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === 'super' ? 'true' : 'false' }}" href="/likes?tab=super">Super Like</a>
<a class="jk-tab {{ $tab === 'favorites' ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === 'favorites' ? 'true' : 'false' }}" href="/likes?tab=favorites">Favorit</a>
</div>

@if($tab === 'received')
<div class="jk-section">
@if(! $isPremium)
<div class="jk-h2">Lihat siapa yang menyukaimu</div>
<p class="jk-muted">Fitur ini khusus Premium. Upgrade untuk melihat daftar lengkap.</p>
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center;margin-top:10px" href="/premium">Upgrade ke Premium</a>
@elseif($received->isEmpty())
@include('components.empty', ['icon' => 'hati', 'title' => 'Belum ada yang menyukaimu', 'hint' => 'Lengkapi profil dan aktif di Discover.'])
@else
<div class="jk-grid">@foreach($received as $l) @if($l->liker) @include('components.profile-card', ['user' => $l->liker, 'compact' => true]) @endif @endforeach</div>
@endif
</div>
@elseif($tab === 'given')
<div class="jk-section"><div class="jk-h2">Kamu menyukai ({{ $given->count() }})</div>
@if($given->isEmpty()) @include('components.empty', ['icon' => 'hati', 'title' => 'Belum memberi like', 'hint' => 'Jelajahi Discover untuk menemukan kecocokan.'])
@else <div class="jk-grid">@foreach($given as $l) @if($l->liked) @include('components.profile-card', ['user' => $l->liked, 'compact' => true]) @endif @endforeach</div>@endif</div>
@elseif($tab === 'super')
<div class="jk-section"><div class="jk-h2">Super Like terkirim ({{ $supers->count() }})</div>
@if($supers->isEmpty()) @include('components.empty', ['icon' => 'bintang', 'title' => 'Belum ada Super Like', 'hint' => 'Gunakan Super Like untuk menonjol di inbox dia.'])
@else <div class="jk-grid">@foreach($supers as $s) @if($s->receiver) @include('components.profile-card', ['user' => $s->receiver, 'compact' => true]) @endif @endforeach</div>@endif</div>
@else
<div class="jk-section"><div class="jk-h2">Favorit ({{ $favs->count() }})</div>
@if($favs->isEmpty()) @include('components.empty', ['icon' => 'bintang', 'title' => 'Belum ada favorit', 'hint' => 'Simpan profil menarik ke favorit.'])
@else <div class="jk-grid">@foreach($favs as $f) @if($f->favorited) @include('components.profile-card', ['user' => $f->favorited, 'compact' => true]) @endif @endforeach</div>@endif</div>
@endif
@livewire('visitor-list')
@endsection
