@extends('layouts.landing')
@section('title', 'Verifikasi Email — Jodohku')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px"><div class="ld-card" style="text-align:center">
<div style="font-size:44px">📧</div><h2 class="ld-h2">Cek email kamu</h2>
<p class="ld-muted">Kami mengirim link verifikasi ke email terdaftar. Klik link untuk mengaktifkan akun dan dapat badge “Terverifikasi email”.</p>
<form method="POST" action="{{ route('verification.send') }}">@csrf<button class="ld-btn" type="submit">Kirim Ulang Email</button></form>
</div></div></section>
@endsection
