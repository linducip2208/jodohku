@extends('layouts.member')
@section('title', 'Discover — Jodohku')
@section('content')
<h1 class="jk-h1">Orang di sekitarmu</h1>
<p class="jk-muted">Rekomendasi berbasis kecocokan, bukan katalog.</p>
@livewire('discover-grid')
@endsection
