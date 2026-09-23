@extends('layouts.member')
@section('title', 'Matches — Jodohku')
@section('content')
<h1 class="jk-h1">Matches</h1>
<p class="jk-muted">Semua yang saling suka. Sapa duluan — kesopanan membuka jalan taaruf.</p>
<div class="jk-tabs" role="tablist" aria-label="Kategori matches">
<a class="jk-tab active" role="tab" aria-selected="true" href="/matches">Matches</a>
<a class="jk-tab" role="tab" aria-selected="false" href="/visitors">Pengunjung</a>
<a class="jk-tab" role="tab" aria-selected="false" href="/likes?tab=favorites">Favorit</a>
</div>
@livewire('match-list')
@endsection
