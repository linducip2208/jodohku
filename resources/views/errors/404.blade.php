@extends('layouts.landing')
@section('title', 'Halaman Tidak Ditemukan (404) — Jodohku')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:560px;text-align:center">
<div class="ld-card">
<div style="font-size:56px;font-weight:800">404</div>
<h2 class="ld-h2">Halaman tidak ditemukan</h2>
<p class="ld-muted">Tautan mungkin sudah dihapus atau kamu salah ketik alamat.</p>
<div style="margin-top:16px;display:flex;gap:10px;justify-content:center">
<a class="ld-btn ghost" href="/">Beranda</a>
<a class="ld-btn" href="/discover">Buka Discover</a>
</div>
</div>
</div></section>
@endsection
