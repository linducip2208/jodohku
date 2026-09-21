@extends('layouts.member')
@section('title', 'Favorit — Jodohku')
@section('content')
<h1 class="jk-h1">⭐ Favorit</h1><p class="jk-muted">Profil yang kamu tandai.</p>
@php $favs = auth()->check() ? auth()->user()->favorites()->with('favoriteUser')->latest('id')->take(24)->get() : collect(); @endphp
@if($favs->isEmpty()) @include('components.empty', ['icon' => '⭐', 'title' => 'Belum ada favorit', 'hint' => 'Tap ⭐ di profil mana pun.']) @else
<div class="jk-grid">@foreach($favs as $f) @php $u = $f->favoriteUser ?? $f->target ?? null; @endphp @if($u) @include('components.profile-card', ['user' => $u, 'compact' => true]) @endif @endforeach</div>@endif
@endsection
