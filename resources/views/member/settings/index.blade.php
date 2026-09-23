@extends('layouts.member')
@section('title', 'Pengaturan — Jodohku')
@section('content')
<h1 class="jk-h1">Pengaturan</h1>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section jk-form"><div class="jk-h2">Profil</div>
<form method="POST" action="/settings/profile">@csrf
<label>Nama tampilan</label><input name="display_name" value="{{ auth()->user()?->display_name }}">
<label>Kota</label><input name="city" value="{{ auth()->user()?->city }}">
<label>Bio</label><textarea name="bio" rows="3">{{ auth()->user()?->profile?->bio }}</textarea>
<label>Pekerjaan</label><input name="occupation" value="{{ auth()->user()?->profile?->occupation }}">
<label>Pendidikan</label><input name="education" value="{{ auth()->user()?->profile?->education }}">
<button class="jk-submit" style="margin-top:12px" type="submit">Simpan Profil</button></form>
</div>
<div class="jk-section jk-form"><div class="jk-h2">Privasi &amp; Pencarian</div>
<form method="POST" action="/settings/privacy">@csrf
<label><input type="checkbox" name="hide_online" value="1"> Sembunyikan status online</label>
<label><input type="checkbox" name="incognito" value="1"> Mode incognito (hanya terlihat oleh yang kamu like)</label>
<label><input type="checkbox" name="hide_distance" value="1"> Sembunyikan jarak</label>
<label><input type="checkbox" name="public_index" value="1"> Profil publik dapat di-index mesin pencari (/u/username)</label>
<button class="jk-submit" style="margin-top:12px" type="submit">Simpan Privasi</button></form>
</div>
<div class="jk-section jk-form"><div class="jk-h2">Notifikasi</div>
<form method="POST" action="/settings/notifications">@csrf
<label><input type="checkbox" name="match" value="1" checked> Match baru</label>
<label><input type="checkbox" name="message" value="1" checked> Pesan baru</label>
<label><input type="checkbox" name="like" value="1" checked> Like baru</label>
<button class="jk-submit" style="margin-top:12px" type="submit">Simpan Notifikasi</button></form>
</div>
<div class="jk-section"><div class="jk-h2">Langganan</div>
<p class="jk-muted">Status: {{ auth()->user()?->isPremium() ? 'Premium aktif' : 'Gratis' }}</p>
<a href="/premium">Kelola Premium →</a>
</div>
<div class="jk-section"><div class="jk-h2">Verifikasi 2 Langkah</div>
<p class="jk-muted">Status: {{ auth()->user()?->two_factor_enabled ? 'Aktif' : 'Nonaktif' }}</p>
@if(auth()->user()?->two_factor_enabled)
<form method="POST" action="/settings/2fa/disable" style="margin-top:8px">@csrf
<label>Password saat ini</label><input type="password" name="password" required>
<button class="jk-btn jk-btn-pass" style="width:100%;margin-top:8px" type="submit">Nonaktifkan 2FA</button></form>
@else
<form method="POST" action="/settings/2fa/enable" style="margin-top:8px">@csrf
<button class="jk-submit" type="submit">Aktifkan 2FA (kode via email)</button></form>
@endif
</div>
<div class="jk-section">
<form method="POST" action="/logout" style="margin-top:0">@csrf<button class="jk-btn jk-btn-pass" style="width:100%" type="submit">Keluar</button></form>
</div>
<div class="jk-section jk-form" aria-labelledby="h-del"><div class="jk-h2" id="h-del">Hapus akun permanen</div>
<p class="jk-muted">Menghapus profil, foto, dan preferensimu. Tindakan ini tidak bisa dibatalkan. Lihat <a href="/privacy">Privasi</a> dan <a href="/guidelines">Panduan Komunitas</a>.</p>
<form method="POST" action="/settings/account" onsubmit="return confirm('Yakin hapus akun permanen?')" style="margin-top:8px">@csrf @method('DELETE')
<label>Password saat ini</label><input type="password" name="password" required autocomplete="current-password">
<button class="jk-btn" style="width:100%;margin-top:8px;background:#b91c1c;color:#fff" type="submit">Hapus akunku</button></form>
</div>
@endsection
