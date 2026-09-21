@extends('layouts.member')
@section('title', ($event->title ?? 'Event') . ' — Jodohku')
@section('content')
<a href="/events" class="jk-muted">← Semua event</a>
<div class="jk-section" style="margin-top:8px"><div class="jk-h2">{{ $event->title ?? 'Event' }}</div>
<div class="jk-muted">{{ $event->city ?? '' }} · {{ isset($event->starts_at) ? $event->starts_at->format('d M Y H:i') : '' }}</div>
<p>{{ $event->description ?? 'Detail event akan diumumkan. Pastikan RSVP agar dapat slot.' }}</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<form method="POST" action="/events/{{ $event->id ?? 1 }}/rsvp">@csrf<button class="jk-submit" type="submit">RSVP Hadir 🎟️</button></form></div>
@endsection
