@extends('layouts.landing')
@section('title', 'Verifikasi Telepon — Jodohku')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px"><div class="ld-card" style="text-align:center">
<div style="font-size:44px">📱</div><h2 class="ld-h2">Verifikasi nomor HP</h2>
<p class="ld-muted">Masukkan kode OTP 6 digit yang dikirim via SMS/WhatsApp.</p>
@if(session('status'))<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px;font-size:13px">{{ session('status') }}</div>@endif
<form method="POST" action="/phone-verify" style="margin-top:12px">@csrf
<input name="code" maxlength="6" placeholder="••••••" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:12px;text-align:center;font-size:22px;letter-spacing:8px">
<button class="ld-btn" style="width:100%;margin-top:12px" type="submit">Verifikasi</button></form>
<form method="POST" action="/phone-verify/send" style="margin-top:8px">@csrf<button class="ld-btn ghost" style="width:100%" type="submit">Kirim Ulang Kode</button></form>
</div></div></section>
@endsection
