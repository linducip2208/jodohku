@extends('layouts.member')
@section('title', 'Kisah Sukses — Jodohku')
@section('content')
<h1 class="jk-h1">Kisah Sukses</h1>
<p class="jk-muted">Pasangan yang dipertemukan Jodohku. <a href="/biro-jodoh/kisah/saya">Kisahku →</a></p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="jk-alert err">{{ $errors->first() }}</div>@endif
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($data as $s)
<div class="jk-section">
<div class="jk-h2">{{ $s->user?->displayName() }} &amp; {{ $s->partner_name }}</div>
<p>{{ $s->story }}</p>
<div class="jk-muted">{{ $s->published_at?->format('d M Y') }}</div>
</div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada kisah yang dipublikasikan.</p></div>
@endforelse
</div>
<div style="margin-top:12px">{{ $data->links() }}</div>
<div class="jk-section jk-form" style="margin-top:12px"><div class="jk-h2">Bagikan kisahmu</div>
<form method="POST" action="/biro-jodoh/kisah">
@csrf
<label for="partner_name">Nama pasangan</label><input id="partner_name" name="partner_name" required maxlength="120">
<label for="story">Kisah (min 50 karakter)</label><textarea id="story" name="story" rows="4" required minlength="50" maxlength="5000"></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Kirim (moderasi admin)</button>
</form></div>
@endsection
