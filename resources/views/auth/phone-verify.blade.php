@extends('layouts.landing')
@section('title', 'Verifikasi Telepon — Jodohku')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px"><div class="ld-card" style="text-align:center">
<div style="font-size:44px" aria-hidden="true">📱</div><h2 class="ld-h2">Verifikasi nomor HP</h2>
<p class="ld-muted">Masukkan nomor HP lalu kode OTP 6 digit yang dikirim via SMS/WhatsApp.</p>
@if($errors->any())<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:13px;color:#b91c1c">{{ $errors->first() }}</div>@endif
@if(session('status'))<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px;font-size:13px">{{ session('status') }}</div>@endif
<form method="POST" action="/phone-verify/send" style="margin-top:12px;text-align:left">@csrf
<label style="font-size:13px;font-weight:700" for="pv-phone">Nomor HP</label>
<input id="pv-phone" name="phone" required value="{{ old('phone', auth()->user()?->phone) }}" placeholder="08…" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<button class="ld-btn ghost" style="width:100%" type="submit">Kirim Kode OTP</button></form>
<form method="POST" action="/phone-verify" style="margin-top:8px;text-align:left" onsubmit="this.querySelector('input[name=phone]').value = document.getElementById('pv-phone').value">@csrf
<input type="hidden" name="phone" value="{{ old('phone', auth()->user()?->phone) }}">
<label style="font-size:13px;font-weight:700" for="pv-otp">Kode OTP</label>
<input id="pv-otp" name="otp" maxlength="6" inputmode="numeric" placeholder="••••••" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:12px;text-align:center;font-size:22px;letter-spacing:8px;margin:6px 0 12px">
<button class="ld-btn" style="width:100%" type="submit">Verifikasi</button></form>
</div></div></section>
@endsection
