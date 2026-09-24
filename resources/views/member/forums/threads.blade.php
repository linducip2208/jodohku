@extends('layouts.member')
@section('title', 'Topik Forum — Jodohku')
@section('content')
@php $forum = null; $threads = collect(); try { $forum = \App\Models\Forum::where('slug', $slug ?? '')->first(); if ($forum) { $threads = $forum->visibleThreads()->with('user')->orderByDesc('is_pinned')->orderByDesc('last_reply_at')->take(20)->get(); } } catch (\Throwable) {} @endphp
@if($forum)
<h1 class="jk-h1">{{ $forum->name }}</h1><p class="jk-muted">{{ $forum->description }}</p>
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($threads as $t)
<div class="jk-section">
<div class="jk-h2">
@if($t->is_pinned)
[Disematkan]
@endif
{{ $t->title }}</div>
<div class="jk-muted">{{ $t->user?->display_name ?? 'Member' }} · {{ $t->reply_count }} balasan
@if($t->is_locked)
· Terkunci
@endif
</div>
<a href="/forums/thread/{{ $t->id }}">Buka →</a></div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada topik. Mulai diskusi pertama di bawah.</p></div>
@endforelse
</div>
<div class="jk-section jk-form" style="margin-top:12px">
<div class="jk-h2">Mulai topik baru</div>
<form method="POST" action="/forums/{{ $forum->slug }}/threads">@csrf
<label for="thread-title">Judul</label><input id="thread-title" name="title" maxlength="220" required>
<label for="thread-body">Isi</label><textarea id="thread-body" name="body" rows="3" required></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Kirim topik</button>
</form>
</div>
<a href="/forums">← Semua forum</a>
@else
<div class="jk-section"><div class="jk-h2">Forum tidak ditemukan</div><a href="/forums">← Kembali</a></div>
@endif
@endsection
