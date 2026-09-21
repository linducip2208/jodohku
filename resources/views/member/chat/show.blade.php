@extends('layouts.member')
@section('title', 'Chat — Jodohku')
@section('content')
<a href="/chat" class="jk-muted">← Inbox</a>
@livewire('chat-window', ['conversationId' => (int) $conversation->id], key('chat-' . $conversation->id))
@endsection
