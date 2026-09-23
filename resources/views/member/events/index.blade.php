@extends('layouts.member')
@section('title', 'Events — Jodohku')
@section('content')
<h1 class="jk-h1">Events</h1><p class="jk-muted">Kopi darat &amp; meetup komunitas.</p>
@php $events = collect(); try { $events = \App\Models\Event::where('status', 'published')->latest('id')->take(12)->get(); } catch (\Throwable) {} @endphp
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($events as $e)
<div class="jk-section"><div class="jk-h2">{{ $e->title }}</div><div class="jk-muted">{{ $e->city }} · {{ $e->starts_at?->format('d M Y H:i') }}</div><p class="jk-muted">{{ \Illuminate\Support\Str::limit($e->description ?? '', 140) }}</p><a href="/events/{{ $e->id }}">Detail &amp; RSVP →</a></div>
@empty
@include('components.empty', ['icon' => 'kalender', 'title' => 'Belum ada event', 'hint' => 'Event komunitas akan muncul di sini. Cek lagi nanti.'])
@endforelse
</div>
@endsection
