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
<div class="jk-section"><strong>{{ $r->user?->displayName() ?? 'Member' }}</strong><p class="jk-muted">{!! nl2br(e($r->body)) !!}</p></div>
@empty
<p class="jk-muted">Belum ada balasan.</p>
@endforelse
</div>
@if(! $thread->is_locked)
<div class="jk-section jk-form" style="margin-top:12px">
<div class="jk-h2">Tulis balasan</div>
<form method="POST" action="/forums/thread/{{ $thread->id }}/reply">@csrf
<label for="reply-body">Balasan</label><textarea id="reply-body" name="body" rows="3" maxlength="10000" required></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Kirim balasan</button>
</form>
</div>
@endif
@else
<div class="jk-section"><div class="jk-h2">Topik tidak ditemukan</div><a href="/forums">← Kembali</a></div>
@endif
@endsection
