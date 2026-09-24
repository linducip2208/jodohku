@extends('layouts.member')
@section('title', 'Saran pertemanan — Jodohku')
@section('content')
<h1 class="jk-h1">Orang yang mungkin kamu kenal</h1>
<p class="jk-muted">Dari minat, lingkaran, dan komunitas yang sama.</p>
<div class="jk-grid">
@forelse($items as $s)
@if($s['user'])
<div>@include('components.profile-card', ['user' => $s['user'], 'compact' => true])
@if(!empty($s['reasons']))<div class="jk-muted" style="font-size:11px;margin-top:4px">{{ implode(' · ', array_slice($s['reasons'], 0, 2)) }}</div>@endif</div>
@endif
@empty @include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada saran', 'hint' => 'Lengkapi minat dan ikuti komunitas.']) @endforelse
</div>
@endsection
