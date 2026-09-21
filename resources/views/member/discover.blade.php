@extends('layouts.member')
@section('title', 'Discover — Jodohku')
@section('content')
<h1 class="jk-h1">🔍 Discover</h1>
<p class="jk-muted">Filter usia, jarak, gender, kota, pendidikan, verified, online, premium + urutan. Didukung DiscoveryService + MatchingEngine.</p>
@livewire('discover-grid')
@endsection
