@extends('layouts.member')
@section('title', 'Chat — Jodohku')
@section('content')
<div class="jk-chat-layout">
<aside class="jk-chat-list-pane" aria-label="Daftar percakapan">
<div class="jk-chat-back"><a href="/chat" class="jk-muted">← Inbox</a></div>
@livewire('chat-inbox', key('chat-inbox-side'))
</aside>
<div class="jk-chat-main-pane">
@livewire('chat-window', ['conversationId' => (int) $conversation->id], key('chat-' . $conversation->id))
</div>
</div>
@endsection
