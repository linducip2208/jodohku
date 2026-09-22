@extends('layouts.member')
@section('title', 'Konselor — Jodohku')
@section('content')
<h1 class="jk-h1">Konselor Biro Jodoh</h1>
<p class="jk-muted">Booking sesi konsultasi pranikah dengan konselor.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="jk-alert err">{{ $errors->first() }}</div>@endif
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($data as $c)
<div class="jk-section">
<div class="jk-h2">{{ $c->user?->displayName() }}</div>
<div class="jk-muted">{{ $c->specialty }}</div>
<p class="jk-muted">{{ \Illuminate\Support\Str::limit($c->bio ?? '', 160) }}</p>
<form method="POST" action="/biro-jodoh/konsultasi">
@csrf
<input type="hidden" name="counselor_id" value="{{ $c->id }}">
<label for="topic-{{ $c->id }}">Topik</label><input id="topic-{{ $c->id }}" name="topic" required maxlength="200" placeholder="cth: Kesiapan menikah">
<label for="scheduled-{{ $c->id }}">Jadwal</label><input id="scheduled-{{ $c->id }}" type="datetime-local" name="scheduled_at" required>
<button class="jk-submit" style="margin-top:10px" type="submit">Booking</button>
</form>
</div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada konselor aktif.</p></div>
@endforelse
</div>
<div style="margin-top:12px">{{ $data->links() }}</div>
<p><a href="/biro-jodoh/konsultasi">Lihat konsultasiku →</a></p>
@endsection
