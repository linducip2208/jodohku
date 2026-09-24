@extends('layouts.member')
@section('title', 'Diskusi — Jodohku')
@section('content')
@php $thread = null; $replies = collect(); try { $thread = \App\Models\ForumThread::with('user')->where('is_hidden', false)->find($threadId ?? 0); if ($thread) { $replies = $thread->visibleReplies()->with('user')->oldest('id')->take(30)->get(); } } catch (\Throwable) {} @endphp
@if($thread)
<h1 class="jk-h1">{{ $thread->title }}</h1>
<p class="jk-muted">Oleh {{ $thread->user?->displayName() ?? 'Member' }}</p>
@if($thread->is_locked)
<p class="jk-muted">Dikunci moderator.</p>
@endif
<div class="jk-section">{!! nl2br(e($thread->body)) !!}</div>
<h2 class="jk-h2">Balasan ({{ $thread->reply_count }})</h2>
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($replies as $r)
<div class="jk-section"><strong>{{ $r->user?->display_name ?? 'Member' }}</strong><p class="jk-muted">{!! nl2br(e($r->body)) !!}</p></div>
@empty
<p class="jk-muted">Belum ada balasan.</p>
@endforelse
</div>
@else
<div class="jk-section"><div class="jk-h2">Topik tidak ditemukan</div><a href="/forums">← Kembali</a></div>
@endif
@endsection
