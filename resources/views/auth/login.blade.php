@extends('layouts.landing')
@section('title', 'Masuk — Jodohku')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px">
<div class="ld-card">
<h2 class="ld-h2">Selamat datang kembali 💖</h2><p class="ld-muted">Masuk untuk lanjutkan pencarian jodohmu.</p>
@if($errors->any())<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:13px;color:#b91c1c">{{ $errors->first() }}</div>@endif
@if(session('status'))<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px;font-size:13px;color:#15803d">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('login.attempt') }}" style="margin-top:14px">
@csrf
<label style="font-size:13px;font-weight:700">Email</label><input name="email" type="email" required value="{{ old('email') }}" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Password</label><input name="password" type="password" required style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px"><input type="checkbox" name="remember"> Ingat saya</label>
<button class="ld-btn" style="width:100%;margin-top:12px" type="submit">Masuk →</button>
</form>
<p style="font-size:13px;margin-top:14px"><a href="{{ route('password.request') }}">Lupa password?</a> · Belum punya akun? <a href="{{ route('register') }}">Daftar gratis</a></p>
</div></div></section>
@endsection
