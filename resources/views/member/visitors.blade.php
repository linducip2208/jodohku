@extends('layouts.member')
@section('title', 'Visitors — Jodohku')
@section('content')
<h1 class="jk-h1">Pengunjung</h1><p class="jk-muted">Siapa yang melihat profilmu.</p>
@livewire('visitor-list')
@endsection
