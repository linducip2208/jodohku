@extends('layouts.member')
@section('title', 'Speed Dating — Jodohku')
@section('content')
<a href="/events/{{ $event->id }}" class="jk-muted">← {{ $event->title }}</a>
<h1 class="jk-h1">⚡ Ronde speed dating saya</h1>
<p class="jk-muted">Tiap ronde: ngobrol {{ (int) ($event->round_minutes ?: 5) }} menit via chat, lalu Like/Lewati. Saling suka = match 💘</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($rounds as $r)
@php $p = $r['partner'] ?? null; @endphp
<div class="jk-section">
<div style="display:flex;gap:10px;align-items:center">
<div class="jk-avatar">@if($p?->avatarUrl())<img src="{{ $p->avatarUrl() }}" alt="Foto {{ $p->displayName() }}">@else{{ strtoupper(substr((string)($p?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1"><strong>Ronde {{ $r['round_no'] }}</strong> · {{ $p?->displayName() ?? '—' }}<br>
<span class="jk-pill" style="font-size:11px">{{ $r['state'] === 'live' ? '● LIVE sekarang' : ($r['state'] === 'done' ? 'Selesai' : 'Mulai '.$r['starts_at']) }}</span></div>
</div>
<div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
@if($r['conversation_id'])<a class="jk-btn jk-btn-like" style="text-decoration:none" href="/chat/{{ $r['conversation_id'] }}">Chat 💬</a>@endif
@if($p)<a class="jk-pill" href="/profile/{{ $p->id }}">Profil</a>@endif
</div>
@if($r['state'] !== 'upcoming' && $p)
<div style="margin-top:8px">@livewire('like-buttons', ['userId' => $p->id], key('speed-like-'.$p->id))</div>
@endif
</div>
@empty
@include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada ronde', 'hint' => 'Host memulai ronde setelah peserta konfirmasi RSVP.'])
@endforelse
</div>
@endsection
