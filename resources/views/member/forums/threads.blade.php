@extends('layouts.member')
@section('title', 'Topik Forum — Jodohku')
@section('content')
@php $forum = null; $threads = collect(); try { $forum = \App\Models\Forum::where('slug', $slug ?? '')->first(); if ($forum) { $threads = $forum->visibleThreads()->with('user')->orderByDesc('is_pinned')->orderByDesc('last_reply_at')->take(20)->get(); } } catch (\Throwable) {} @endphp
@if($forum)
<h1 class="jk-h1">{{ $forum->name }}</h1><p class="jk-muted">{{ $forum->description }}</p>
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($threads as $t)
<div class="jk-section"><div class="jk-h2">@if($t->is_pinned)📌 @endif{{ $t->title }}</div><div class="jk-muted">{{ $t->user?->display_name ?? 'Member' }} · {{ $t->reply_count }} balasan @if($t->is_locked)🔒@endif</div><a href="/forums/thread/{{ $t->id }}">Buka →</a></div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada topik. Jadilah yang pertama via API.</p></div>
@endforelse
</div>
<a href="/forums">← Semua forum</a>
@else
<div class="jk-section"><div class="jk-h2">Forum tidak ditemukan</div><a href="/forums">← Kembali</a></div>
@endif
@endsection
