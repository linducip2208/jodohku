@extends('layouts.member')
@section('title', 'Notifikasi — Jodohku')
@section('content')
@php
$cat = request('cat', 'semua');
$cats = ['semua' => 'Semua', 'match' => 'Kecocokan', 'pesan' => 'Pesan', 'taaruf' => 'Taaruf', 'komunitas' => 'Komunitas', 'sistem' => 'Sistem'];
$notes = collect();
try {
    if (auth()->check()) {
        $notes = auth()->user()->notifications()->latest('id')->take(60)->get();
        if ($cat !== 'semua') {
            $notes = $notes->filter(function ($n) use ($cat) {
                $type = strtolower(class_basename($n->type) . ' ' . json_encode($n->data));
                return match ($cat) {
                    'match' => str_contains($type, 'match') || str_contains($type, 'like') || str_contains($type, 'super'),
                    'pesan' => str_contains($type, 'message') || str_contains($type, 'chat') || str_contains($type, 'call'),
                    'taaruf' => str_contains($type, 'courtship') || str_contains($type, 'consultation') || str_contains($type, 'taaruf'),
                    'komunitas' => str_contains($type, 'event') || str_contains($type, 'forum') || str_contains($type, 'blog') || str_contains($type, 'post'),
                    default => ! (str_contains($type, 'match') || str_contains($type, 'message') || str_contains($type, 'chat') || str_contains($type, 'courtship') || str_contains($type, 'consultation')),
                };
            })->values();
        }
    }
} catch (\Throwable) {}
@endphp
<h1 class="jk-h1">Notifikasi</h1>
<p class="jk-muted">Semua kabar penting: kecocokan, pesan, taaruf, komunitas, dan sistem.</p>
<div class="jk-tabs" role="tablist" aria-label="Kategori notifikasi">
@foreach($cats as $key => $label)
<a class="jk-tab {{ $cat === $key ? 'active' : '' }}" role="tab" aria-selected="{{ $cat === $key ? 'true' : 'false' }}" href="/notifications?cat={{ $key }}">{{ $label }}</a>
@endforeach
</div>
<div class="jk-chat-list" role="list">
@forelse($notes->take(30) as $n)
<div class="jk-chat-item" role="listitem" style="{{ $n->read_at ? 'opacity:.75' : '' }}"><div class="jk-avatar sm" aria-hidden="true">{{ strtoupper(substr((string)($n->data['title'] ?? class_basename($n->type)), 0, 1)) }}</div><div style="flex:1"><strong>{{ $n->data['title'] ?? str_replace(['MatchFound', 'NewMessage', 'ChatRequestReceived', 'CourtshipStageChanged', 'ConsultationStatusChanged', 'BroadcastMessage'], ['Kecocokan baru', 'Pesan baru', 'Permintaan chat', 'Update taaruf', 'Update konsultasi', 'Pengumuman'], class_basename($n->type)) }}</strong><div class="jk-muted">{{ $n->data['body'] ?? $n->data['title'] ?? '' }}</div><div class="jk-muted" style="font-size:11px">{{ $n->created_at?->diffForHumans() }}{{ $n->read_at ? ' · dibaca' : ' · baru' }}</div></div></div>
@empty @include('components.empty', ['icon' => 'lonceng', 'title' => 'Belum ada notifikasi', 'hint' => 'Kecocokan, like, dan pesan akan muncul di sini.']) @endforelse
</div>
@if(auth()->check())
<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
<form method="POST" action="/notifications/read-all">@csrf<button class="jk-btn jk-btn-pass" type="submit">Tandai semua dibaca</button></form>
<a class="jk-pill" href="/settings">Atur preferensi notifikasi</a>
</div>
@endif
@endsection
