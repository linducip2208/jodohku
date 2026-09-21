@extends('layouts.landing')
@section('title', 'Daftar — Jodohku')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:480px">
<div class="ld-card">
<h2 class="ld-h2">Buat akun gratis 🎉</h2><p class="ld-muted">2 menit, langsung dapat rekomendasi cocok.</p>
@if($errors->any())<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px;font-size:13px;color:#b91c1c">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register.store') }}" style="margin-top:14px">
@csrf
<label style="font-size:13px;font-weight:700">Nama</label><input name="name" required value="{{ old('name') }}" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Email</label><input name="email" type="email" required value="{{ old('email') }}" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Tanggal lahir</label><input name="date_of_birth" type="date" required style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Gender</label><select name="gender" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px"><option value="female">Perempuan</option><option value="male">Laki-laki</option></select>
<label style="font-size:13px;font-weight:700">Password</label><input name="password" type="password" required style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Konfirmasi password</label><input name="password_confirmation" type="password" required style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<button class="ld-btn" style="width:100%" type="submit">Daftar &amp; Mulai Match →</button>
</form>
<p style="font-size:13px;margin-top:14px">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
</div></div></section>
@endsection
