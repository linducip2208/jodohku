@extends('layouts.member')
@section('title', 'Notifikasi — Jodohku')
@section('content')
<h1 class="jk-h1">🔔 Notifikasi</h1>
@php $notes = auth()->check() ? auth()->user()->notifications()->take(30)->get() : collect(); @endphp
<div class="jk-chat-list">
@forelse($notes as $n)
<div class="jk-chat-item"><div style="font-size:24px">🔔</div><div><strong>{{ $n->data['title'] ?? class_basename($n->type) }}</strong><div class="jk-muted">{{ $n->data['body'] ?? '' }}</div><div class="jk-muted" style="font-size:11px">{{ $n->created_at?->diffForHumans() }}</div></div></div>
@empty @include('components.empty', ['icon' => '🔔', 'title' => 'Belum ada notifikasi', 'hint' => 'Match, like, dan pesan akan muncul di sini.']) @endforelse
</div>
@if(auth()->check())
<form method="POST" action="/notifications/read-all" style="margin-top:12px">@csrf<button class="jk-btn jk-btn-pass" type="submit">Tandai semua dibaca</button></form>
@endif
@endsection
