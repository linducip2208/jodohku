@extends('layouts.member')
@section('title', 'Forum — Jodohku')
@section('content')
<h1 class="jk-h1">Forum</h1><p class="jk-muted">Diskusi komunitas: dimoderasi, tanpa konten dewasa.</p>
@php $forums = collect(); try { $forums = \App\Models\Forum::where('is_active', true)->orderBy('sort_order')->withCount(['visibleThreads as threads_count'])->get(); } catch (\Throwable) {} @endphp
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($forums as $f)
<div class="jk-section"><div class="jk-h2">{{ $f->name }}</div><p class="jk-muted">{{ $f->description }} · {{ $f->threads_count }} topik</p><a href="/forums/{{ $f->slug }}">Buka forum →</a></div>
@empty
<div class="jk-section"><div class="jk-h2">💍 Persiapan Nikah</div><p class="jk-muted">Belum ada forum. Admin dapat membuatnya dari /admin.</p></div>
@endforelse
</div>
@endsection
