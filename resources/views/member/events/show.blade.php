@extends('layouts.member')
@section('title', ($event->title ?? 'Event') . ' — Jodohku')
@section('content')
<a href="/events" class="jk-muted">← Semua event</a>
<div class="jk-section" style="margin-top:8px"><div class="jk-h2">{{ $event->title ?? 'Event' }}</div>
<div class="jk-muted">{{ $event->city ?? '' }} · {{ isset($event->starts_at) ? $event->starts_at->format('d M Y H:i') : '' }}</div>
<p>{{ $event->description ?? 'Detail event akan diumumkan. Pastikan RSVP agar dapat slot.' }}</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@php $myRsvp = auth()->check() ? \App\Models\EventMember::where('event_id', $event->id ?? 0)->where('user_id', auth()->id())->value('status') : null; @endphp
<div style="display:flex;gap:8px;flex-wrap:wrap" role="group" aria-label="RSVP">
@foreach(['confirmed' => 'Hadir ✓', 'maybe' => 'Mungkin', 'declined' => 'Batal'] as $st => $label)
<form method="POST" action="/events/{{ $event->id ?? 1 }}/rsvp" style="display:inline">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="jk-pill" @if($myRsvp === $st) style="background:#f43f5e;color:#fff" @endif type="submit" aria-pressed="{{ $myRsvp === $st ? 'true' : 'false' }}">{{ $label }}</button></form>
@endforeach
</div>
@if(($event->format ?? 'meetup') === 'speed_dating')
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
<a class="jk-btn jk-btn-like" style="text-decoration:none" href="/events/{{ $event->id }}/speed">⚡ Ronde saya</a>
@if(auth()->check() && (int) $event->host_id === (int) auth()->id())
<form method="POST" action="/events/{{ $event->id }}/speed/start" style="display:inline">@csrf<button class="jk-btn" type="submit">Mulai ronde</button></form>
@endif
</div>
@endif
</div>
@endsection
