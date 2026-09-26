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
@php $pp = auth()->user()?->profilePrivacy; $sel = fn ($v) => (string) ($pp?->$v?->value ?? $pp?->$v ?? 'public'); @endphp
<label><input type="checkbox" name="hide_online" value="1"> Sembunyikan status online</label>
<label><input type="checkbox" name="incognito" value="1"> Mode incognito (hanya terlihat oleh yang kamu like)</label>
<label><input type="checkbox" name="hide_distance" value="1"> Sembunyikan jarak</label>
<label><input type="checkbox" name="public_index" value="1"> Profil publik dapat di-index mesin pencari (/u/username)</label>
<label>Siapa bisa lihat pengikutmu
<select name="followers_visibility">@foreach(['public' => 'Semua', 'members_only' => 'Member', 'matches_only' => 'Match saja', 'private' => 'Hanya saya'] as $v => $l)<option value="{{ $v }}" @selected($sel('followers_visibility') === $v)>{{ $l }}</option>@endforeach</select></label>
<label>Siapa bisa lihat yang kamu ikuti
<select name="following_visibility">@foreach(['public' => 'Semua', 'members_only' => 'Member', 'matches_only' => 'Match saja', 'private' => 'Hanya saya'] as $v => $l)<option value="{{ $v }}" @selected($sel('following_visibility') === $v)>{{ $l }}</option>@endforeach</select></label>
<label>Siapa bisa lihat postinganmu
<select name="posts_visibility">@foreach(['public' => 'Semua', 'members_only' => 'Member', 'matches_only' => 'Match saja', 'private' => 'Hanya saya'] as $v => $l)<option value="{{ $v }}" @selected($sel('posts_visibility') === $v)>{{ $l }}</option>@endforeach</select></label>
<label>Siapa bisa lihat story-mu
<select name="stories_visibility">@foreach(['public' => 'Semua', 'members_only' => 'Member', 'matches_only' => 'Match saja', 'private' => 'Hanya saya'] as $v => $l)<option value="{{ $v }}" @selected($sel('stories_visibility') === $v)>{{ $l }}</option>@endforeach</select></label>
<button class="jk-submit" style="margin-top:12px" type="submit">Simpan Privasi</button></form>
</div>
<div class="jk-section jk-form"><div class="jk-h2">Notifikasi</div>
@php $np = auth()->user()?->notificationPreference; @endphp
<form method="POST" action="/settings/notifications">@csrf
<label><input type="checkbox" name="match" value="1" @checked(($np?->push_matches ?? true))> Match baru</label>
<label><input type="checkbox" name="message" value="1" @checked(($np?->push_messages ?? true))> Pesan baru</label>
<label><input type="checkbox" name="like" value="1" @checked(($np?->push_likes ?? true))> Like baru</label>
<label><input type="checkbox" name="follow" value="1" @checked(($np?->push_follows ?? true))> Pengikut baru</label>
<label><input type="checkbox" name="comment" value="1" @checked(($np?->push_comments ?? true))> Komentar &amp; reaksi</label>
<label><input type="checkbox" name="mention" value="1" @checked(($np?->push_mentions ?? true))> Mention</label>
<label><input type="checkbox" name="date" value="1" @checked(($np?->push_dates ?? true))> Ajakan & pengingat kencan</label>
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
<div class="jk-section"><div class="jk-h2">Aplikasi Authenticator</div>
@php $hasTotp = false; try { $hasTotp = app(\App\Services\TwoFactorService::class)->hasTotp(auth()->user()); } catch (\Throwable) {} @endphp
<p class="jk-muted">Status: {{ $hasTotp ? 'Terhubung ✅' : 'Belum terhubung' }} — lebih aman dari kode email, tetap bisa login offline.</p>
@if(session('totp_setup'))
@php $ts = session('totp_setup'); @endphp
<div class="jk-alert ok">Pindai ke aplikasi authenticator (Google/Microsoft Authy):<br>
<img src="/settings/2fa/totp/qr" alt="QR kode authenticator" style="width:200px;height:200px;background:#fff;border-radius:12px;margin:8px 0" loading="lazy" onerror="this.remove()">
<br><code style="word-break:break-all">{{ $ts['secret'] ?? '' }}</code><br>
<button class="jk-pill" type="button" onclick="navigator.clipboard?.writeText('{{ $ts['secret'] ?? '' }}').then(()=>window.jkToast?.('Secret disalin ✅'))">Salin secret</button>
<a class="jk-pill" href="{{ $ts['otpauth_url'] ?? '#' }}" style="text-decoration:none">Buka di aplikasi</a></div>
<form method="POST" action="/settings/2fa/totp/confirm" class="jk-form" style="margin-top:8px">@csrf
<label>Kode 6 digit dari aplikasi</label><input name="code" inputmode="numeric" maxlength="6" required placeholder="123456">
<button class="jk-submit" type="submit">Verifikasi & Aktifkan</button></form>
@elseif($hasTotp)
<form method="POST" action="/settings/2fa/totp/backup" style="margin-top:8px">@csrf
<label>Password saat ini</label><input type="password" name="password" required>
<button class="jk-btn jk-btn-like" style="width:100%;margin-top:8px" type="submit">Buat backup codes baru</button></form>
@else
<form method="POST" action="/settings/2fa/totp/start" style="margin-top:8px">@csrf
<button class="jk-submit" type="submit">Hubungkan authenticator</button></form>
@endif
@if(session('backup_codes'))
<div class="jk-alert ok">Simpan sekali saja:<br><code>@foreach((array) session('backup_codes') as $bc){{ $bc }}<br>@endforeach</code></div>
@endif
@if($errors->has('code'))<div class="jk-alert">{{ $errors->first('code') }}</div>@endif
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
