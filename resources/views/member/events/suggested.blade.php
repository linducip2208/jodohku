@extends('layouts.member')
@section('title', 'Kenalan di Event — Jodohku')
@section('content')
<h1 class="jk-h1">Kenalan di {{ $event->title }}</h1>
<p class="jk-muted">Peserta terkonfirmasi yang bisa kamu sapa. Privasi tetap dijaga — tanpa lokasi exact & tanpa foto privat.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-grid">
@forelse($suggested as $u)
@include('components.profile-card', ['user' => $u, 'compact' => true])
@empty
@include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada saran', 'hint' => 'Jadilah yang pertama konfirmasi RSVP, lalu ajak temanmu.'])
@endforelse
</div>
@if(method_exists($suggested, 'links'))
<div style="margin-top:16px">{{ $suggested->links() }}</div>
@endif
@endsection
