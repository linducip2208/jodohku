@extends('layouts.member')
@section('title', 'Pusat Keamanan — Jodohku')
@section('content')
<h1 class="jk-h1">Pusat Keamanan</h1><p class="jk-muted">Blokir, laporkan, sembunyikan, incognito. Jangan pernah kirim uang ke orang baru kenal.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px"><a class="jk-pill" href="/kontak-blokir">Blokir kontak HP</a><a class="jk-pill" href="/privasi">Pusat Privasi →</a></div>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if(!empty($safetyStatus))
<div class="jk-section"><div class="jk-h2">Status Keamanan Akunmu</div>
<table class="jk-table"><tbody>
<tr><td>Email terverifikasi</td><td>{{ $safetyStatus['email_verified'] ? 'Ya' : 'Belum' }}</td></tr>
<tr><td>Identitas terverifikasi</td><td>{{ $safetyStatus['is_verified'] ? 'Ya' : 'Belum' }}</td></tr>
<tr><td>Verifikasi 2 langkah</td><td>{{ $safetyStatus['two_factor'] ? 'Aktif' : 'Mati' }} (<a href="/settings">atur</a>)</td></tr>
<tr><td>Mode incognito</td><td>{{ $safetyStatus['incognito'] ? 'Aktif' : 'Mati' }}</td></tr>
<tr><td>Pengajuan verifikasi</td><td>{{ count($safetyStatus['verification_requests'] ?? []) }} pengajuan</td></tr>
<tr><td>Blokir &amp; laporan dibuat</td><td>{{ $safetyStatus['blocks_count'] }} blokir, {{ $safetyStatus['reports_count'] }} laporan</td></tr>
</tbody></table>
</div>
@endif
<div class="jk-section"><div class="jk-h2">Edukasi Aman</div>
<ul class="jk-muted">
<li>Jangan pernah kirim OTP, password, atau data bank ke siapa pun.</li>
<li>Jangan transfer uang ke orang yang baru dikenal.</li>
<li>Waspadai ajakan investasi dan pindah platform.</li>
<li>Pertemuan pertama sebaiknya di tempat umum dan beri tahu keluarga.</li>
<li>Laporkan akun mencurigakan — tim kami menindaklanjuti.</li>
</ul>
</div>
<div class="jk-section jk-form"><div class="jk-h2">⛔ Blokir user</div>
<form method="POST" action="/safety/block">@csrf<label>ID / Username user</label><input name="user_id" required placeholder="cth: 12"><button class="jk-submit" style="margin-top:10px" type="submit">Blokir</button></form></div>
<div class="jk-section jk-form"><div class="jk-h2">🚩 Laporkan user</div>
<form method="POST" action="/safety/report">@csrf<label>ID user</label><input name="user_id" required><label>Alasan</label><select name="reason"><option>Spam/scam</option><option>Foto palsu</option><option>Pelecehan</option><option>Lainnya</option></select><label>Detail</label><textarea name="details" rows="2"></textarea><button class="jk-submit" style="margin-top:10px" type="submit">Kirim Laporan</button></form></div>
<div class="jk-section"><div class="jk-h2">Daftar blokir</div>
@php $blocks = auth()->check() ? auth()->user()->blocksInitiated()->with('blocked')->take(20)->get() : collect(); @endphp
<table class="jk-table"><thead><tr><th>User</th><th>Aksi</th></tr></thead><tbody>@forelse($blocks as $b)<tr><td>{{ $b->blocked?->displayName() ?? $b->blocked_id }}</td><td><form method="POST" action="/safety/unblock">@csrf<input type="hidden" name="user_id" value="{{ $b->blocked_id }}"><button class="jk-pill" type="submit">Buka blokir</button></form></td></tr>@empty<tr><td colspan="2" class="jk-muted">Tidak ada yang diblokir.</td></tr>@endforelse</tbody></table>
</div>
@endsection
