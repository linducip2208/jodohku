@extends('layouts.member')
@section('title', 'Events — Jodohku')
@section('content')
<h1 class="jk-h1">Events</h1><p class="jk-muted">Kopi darat &amp; meetup komunitas.</p>
<details class="jk-section" style="margin-bottom:12px"><summary class="jk-h2" style="cursor:pointer">＋ Buat event</summary>
<form method="POST" action="/events" class="jk-form" style="margin-top:10px">@csrf
<label>Judul</label><input name="title" required maxlength="150" placeholder="Kopi darat Jakarta Selatan">
<label>Deskripsi</label><textarea name="description" rows="2" maxlength="2000" placeholder="Detail acara…"></textarea>
<label>Kota</label><input name="city" maxlength="120" value="{{ auth()->user()?->city }}">
<label>Mulai</label><input name="starts_at" type="datetime-local" required>
<label>Selesai (opsional)</label><input name="ends_at" type="datetime-local">
<label>Kapasitas (opsional)</label><input name="capacity" type="number" min="2" max="10000">
<label><input type="checkbox" name="is_online" value="1"> Event online</label>
<button class="jk-submit" style="margin-top:10px" type="submit">Terbitkan</button></form>
</details>
@php $events = collect(); try { $events = \App\Models\Event::where('status', 'published')->latest('id')->take(12)->get(); } catch (\Throwable) {} @endphp
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($events as $e)
<div class="jk-section"><div class="jk-h2">{{ $e->title }}</div><div class="jk-muted">{{ $e->city }} · {{ $e->starts_at?->format('d M Y H:i') }}</div><p class="jk-muted">{{ \Illuminate\Support\Str::limit($e->description ?? '', 140) }}</p><a href="/events/{{ $e->id }}">Detail &amp; RSVP →</a></div>
@empty
@include('components.empty', ['icon' => 'kalender', 'title' => 'Belum ada event', 'hint' => 'Event komunitas akan muncul di sini. Cek lagi nanti.'])
@endforelse
</div>
@endsection
