@extends('layouts.member')
@section('title', 'Pesan tersimpan — Jodohku')
@section('content')
<a href="/chat" class="jk-muted">← Inbox</a>
<h1 class="jk-h1" style="margin-top:8px">Pesan tersimpan</h1>
<p class="jk-muted">Pesan penting yang kamu simpan dari percakapan. Hanya kamu yang bisa melihat daftar ini.</p>
<div class="jk-chat-list">
@forelse($bookmarks as $b)
<div class="jk-chat-item"><div style="flex:1;min-width:0">
<div class="jk-muted" style="font-size:11px">{{ $b->message?->sender?->displayName() ?? 'Member' }} · {{ $b->created_at?->diffForHumans() }}</div>
<div style="margin-top:2px">{{ \Illuminate\Support\Str::limit((string) $b->message?->body, 200) }}</div>
@if($b->message)<div style="margin-top:6px"><a class="jk-pill" href="/chat/{{ $b->message->conversation_id }}">Buka percakapan</a></div>@endif
</div></div>
@empty
@include('components.empty', ['icon' => 'bintang', 'title' => 'Belum ada pesan tersimpan', 'hint' => 'Tekan Simpan pada pesan mana pun di chat.'])
@endforelse
</div>
<div style="margin-top:12px">{{ $bookmarks->links() }}</div>
@endsection
