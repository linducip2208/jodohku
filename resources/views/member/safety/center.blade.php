@extends('layouts.member')
@section('title', 'Pusat Keamanan — Jodohku')
@section('content')
<h1 class="jk-h1">🛡️ Pusat Keamanan</h1><p class="jk-muted">Blokir, laporkan, sembunyikan, incognito. Jangan pernah kirim uang ke orang baru kenal.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section jk-form"><div class="jk-h2">⛔ Blokir user</div>
<form method="POST" action="/safety/block">@csrf<label>ID / Username user</label><input name="user_id" required placeholder="cth: 12"><button class="jk-submit" style="margin-top:10px" type="submit">Blokir</button></form></div>
<div class="jk-section jk-form"><div class="jk-h2">🚩 Laporkan user</div>
<form method="POST" action="/safety/report">@csrf<label>ID user</label><input name="user_id" required><label>Alasan</label><select name="reason"><option>Spam/scam</option><option>Foto palsu</option><option>Pelecehan</option><option>Lainnya</option></select><label>Detail</label><textarea name="details" rows="2"></textarea><button class="jk-submit" style="margin-top:10px" type="submit">Kirim Laporan</button></form></div>
<div class="jk-section"><div class="jk-h2">Daftar blokir</div>
@php $blocks = auth()->check() ? auth()->user()->blocksInitiated()->with('blocked')->take(20)->get() : collect(); @endphp
<table class="jk-table"><thead><tr><th>User</th><th>Aksi</th></tr></thead><tbody>@forelse($blocks as $b)<tr><td>{{ $b->blocked?->displayName() ?? $b->blocked_id }}</td><td><form method="POST" action="/safety/unblock">@csrf<input type="hidden" name="user_id" value="{{ $b->blocked_id }}"><button class="jk-pill" type="submit">Buka blokir</button></form></td></tr>@empty<tr><td colspan="2" class="jk-muted">Tidak ada yang diblokir.</td></tr>@endforelse</tbody></table>
</div>
@endsection
