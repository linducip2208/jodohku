@extends('layouts.member')
@section('title', 'Matches — Jodohku')
@section('content')
<h1 class="jk-h1">💘 Matches</h1>
<p class="jk-muted">Semua yang saling suka. Langsung chat!</p>
@livewire('match-list')
@endsection
