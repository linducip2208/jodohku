@extends('layouts.member')
@section('title', 'Chat — Jodohku')
@section('content')
<h1 class="jk-h1">💬 Chat</h1>
<p class="jk-muted">Filter: Semua · Belum dibaca · Match · Request · Favorit · Arsip · Mute · Online · Premium · Verified · Belum dibalas · Terbaru · Lampiran + pencarian. Didukung ChatService + Reverb.</p>
@livewire('chat-inbox')
@endsection
