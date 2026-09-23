@extends('layouts.landing')
@section('title', 'Lupa Password — Jodohku')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px"><div class="ld-card">
<h2 class="ld-h2">Lupa password? 🔑</h2><p class="ld-muted">Masukkan email, kami kirim link reset.</p>
@if(session('status'))<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px;font-size:13px">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" style="margin-top:12px">@csrf
<input name="email" type="email" required placeholder="nama@email.com" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px">
<button class="ld-btn" style="width:100%;margin-top:12px" type="submit">Kirim Link Reset</button></form>
<p style="font-size:13px;margin-top:12px"><a href="{{ route('login') }}">← Kembali masuk</a></p>
</div></div></section>
@endsection
