@extends('layouts.member')
@section('title', 'Artikel — Jodohku')
@section('content')
@php $post = null; try { $post = \App\Models\BlogPost::published()->where('slug', $slug ?? '')->first(); if ($post) { $post->recordView(); $post = $post->fresh(); } } catch (\Throwable) {} @endphp
@if($post)
<h1 class="jk-h1">{{ $post->title }}</h1>
<p class="jk-muted">{{ $post->published_at?->format('d M Y') }} · {{ number_format($post->view_count) }} dibaca</p>
<div class="jk-section">{!! nl2br(e($post->body)) !!}</div>
<a href="/blog">← Semua artikel</a>
@else
<div class="jk-section"><div class="jk-h2">Artikel tidak ditemukan</div><p class="jk-muted">Mungkin sudah diarsipkan admin.</p><a href="/blog">← Kembali</a></div>
@endif
@endsection
