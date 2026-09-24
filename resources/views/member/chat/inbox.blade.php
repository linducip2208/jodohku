@extends('layouts.member')
@section('title', 'Chat — Jodohku')
@section('content')
<h1 class="jk-h1">Chat</h1>
<p class="jk-muted">Semua percakapanmu dalam satu tempat — realtime dan aman.</p>
@livewire('chat-inbox')
@endsection
