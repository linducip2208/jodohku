@extends('layouts.landing')
@section('title', 'Verifikasi 2 Langkah — Jodohku')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px">
<div class="ld-card">
<h2 class="ld-h2">🔐 Verifikasi 2 Langkah</h2><p class="ld-muted">Masukkan 6 digit kode dari email kamu.</p>
@if($errors->any())<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:13px;color:#b91c1c">{{ $errors->first() }}</div>@endif
@if(session('status'))<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px;font-size:13px;color:#15803d">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('2fa.verify') }}" style="margin-top:14px">
@csrf
<label style="font-size:13px;font-weight:700">Kode verifikasi</label><input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px;letter-spacing:6px;text-align:center;font-size:20px;font-weight:800">
<button class="ld-btn" style="width:100%" type="submit">Verifikasi</button>
</form>
<form method="POST" action="{{ route('2fa.resend') }}" style="margin-top:10px">
@csrf
<button type="submit" style="background:none;border:0;color:#e11d48;font-size:13px;cursor:pointer">Kirim ulang kode</button>
</form>
</div></div></section>
@endsection
