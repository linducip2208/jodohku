@extends('layouts.member')
@section('title', 'Blog — Jodohku')
@section('content')
<h1 class="jk-h1">📝 Blog</h1><p class="jk-muted">Tips kencan, hubungan &amp; persiapan nikah.</p>
@php $posts = collect(); try { $posts = \App\Models\BlogPost::published()->latest('published_at')->take(12)->get(); } catch (\Throwable) {} @endphp
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($posts as $p)
<div class="jk-section"><div class="jk-h2">{{ $p->title }}</div><div class="jk-muted">{{ $p->published_at?->format('d M Y') }} · {{ number_format($p->view_count) }} dibaca</div><p class="jk-muted">{{ $p->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($p->body), 140) }}</p><a href="/blog/{{ $p->slug }}">Baca →</a></div>
@empty
<div class="jk-section"><div class="jk-h2">💬 10 Pembuka Obrolan Pertama yang Sopan</div><div class="jk-muted">Contoh ice-breaker yang terbukti dibalas.</div><a href="/blog">Baca →</a></div>
<div class="jk-section"><div class="jk-h2">💍 Checklist Siap Nikah</div><div class="jk-muted">Persiapan mental, finansial &amp; keluarga.</div><a href="/blog">Baca →</a></div>
@endforelse
</div>
@endsection
