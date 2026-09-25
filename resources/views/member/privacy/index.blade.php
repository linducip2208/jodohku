@extends('layouts.member')
@section('title', 'Pusat Privasi — Jodohku')
@section('content')
<h1 class="jk-h1">Pusat Privasi</h1>
<p class="jk-muted">Semua kontrol privasi dan datamu dalam satu tempat.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section"><div class="jk-h2">Visibilitas</div>
<p class="jk-muted" style="margin:0 0 8px">Atur siapa yang bisa melihat profil, foto, dan statusmu.</p>
<a class="jk-pill" href="/settings">Pengaturan privasi →</a></div>
<div class="jk-section"><div class="jk-h2">Lokasi &amp; Passport</div>
<a class="jk-pill" href="/passport">Mode Passport →</a></div>
<div class="jk-section"><div class="jk-h2">Diblokir ({{ $blocks->count() }})</div>
@forelse($blocks as $b)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $b->blocked?->displayName() ?? 'Akun' }}</strong></div>
<form method="POST" action="/safety/unblock" style="display:inline">@csrf<input type="hidden" name="user_id" value="{{ $b->blocked_id }}"><button class="jk-pill" type="submit">Buka blokir</button></form></div>
@empty<p class="jk-muted" style="margin:0">Tidak ada akun diblokir.</p>@endforelse
</div>
<div class="jk-section"><div class="jk-h2">Dibisukan ({{ $mutes->count() }})</div>
@forelse($mutes as $m)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $m->muted?->displayName() ?? 'Akun' }}</strong></div>
<form method="POST" action="/bisukan/{{ $m->muted_id }}" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Batalkan bisu</button></form></div>
@empty<p class="jk-muted" style="margin:0">Tidak ada akun dibisukan.</p>@endforelse
</div>
<div class="jk-section"><div class="jk-h2">Kontak diblokir</div>
<p class="jk-muted" style="margin:0 0 8px">{{ $contactStatus['hashes'] }} hash · {{ $contactStatus['matched'] }} cocok. Nomor mentah tidak pernah disimpan.</p>
<a class="jk-pill" href="/kontak-blokir">Kelola →</a></div>
<div class="jk-section"><div class="jk-h2">Notifikasi</div>
<a class="jk-pill" href="/settings">Preferensi notifikasi →</a></div>
<div class="jk-section"><div class="jk-h2">Sesi login ({{ $sessions->count() }})</div>
@forelse($sessions as $s)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $s->ip_address ?? 'IP tak dikenal' }}</strong><div class="jk-muted">{{ \Illuminate\Support\Str::limit($s->user_agent ?? '', 60) }} · {{ $s->last_activity ? \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans() : '' }}</div></div>
<form method="POST" action="/privasi/sesi/{{ $s->id }}" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Cabut</button></form></div>
@empty<p class="jk-muted" style="margin:0">Tidak ada sesi tercatat.</p>@endforelse
</div>
<div class="jk-section"><div class="jk-h2">Data &amp; akun</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<a class="jk-pill" href="/privasi/ekspor">Unduh dataku (JSON)</a>
@if(auth()->user()?->is_paused)
<form method="POST" action="/privasi/lanjut" style="display:inline">@csrf<button class="jk-pill" type="submit">Aktifkan kembali akun</button></form>
@else
<form method="POST" action="/privasi/jeda" style="display:inline" onsubmit="return confirm('Jeda akun? Profilmu disembunyikan sampai diaktifkan lagi.')">@csrf<button class="jk-pill" type="submit">Jeda akun</button></form>
@endif
<a class="jk-pill" href="/settings">Hapus akun →</a>
</div></div>
@endsection
