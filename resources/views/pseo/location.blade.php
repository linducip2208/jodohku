@extends('layouts.landing')
@section('title', 'Biro Jodoh ' . $city['name'] . ' — Taaruf & Matchmaking Serius')
@section('meta_description', 'Biro jodoh ' . $city['name'] . ', ' . $city['province'] . ': ' . number_format($count) . ' member aktif mencari pasangan serius. Matchmaking, Smart Taaruf, dan komunitas aman.')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:780px">
<p class="ld-muted"><a href="/">Beranda</a> / <a href="/biro-jodoh">Biro Jodoh</a> / {{ $city['name'] }}</p>
<h1 class="ld-h2">Biro Jodoh {{ $city['name'] }}</h1>
<p class="ld-sub">{{ $city['intro'] }}</p>
<div class="ld-card" style="margin-top:16px">
<p class="ld-muted" style="margin:0"><strong style="color:#18181b">{{ number_format($count) }} member aktif</strong> di {{ $city['name'] }} dan sekitarnya ({{ $city['province'] }}), dari total {{ number_format($totalMembers) }} member nasional. Profil member bersifat privat — halaman ini hanya memuat informasi umum kota.</p>
<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap"><a class="ld-btn" href="/register">Daftar gratis</a><a class="ld-btn ghost" href="/taaruf">Pelajari taaruf</a></div>
</div>
<h2 class="ld-h2" style="margin-top:28px">Cara memulai di {{ $city['name'] }}</h2>
<div class="ld-card"><ol class="ld-muted">
<li>Daftar dan lengkapi profil + foto terverifikasi.</li>
<li>Gunakan filter kota {{ $city['name'] }} dan tujuan hubungan "menikah".</li>
<li>Mulai dari topik visi — lihat <a href="/panduan/pertanyaan-taaruf">pertanyaan taaruf</a>.</li>
<li>Libatkan wali sejak tahap taaruf; ikuti <a href="/biro-jodoh">event komunitas</a> bila ada.</li>
</ol></div>
<h2 class="ld-h2" style="margin-top:28px">Kota terdekat</h2>
<p class="ld-muted">@foreach($nearby as $n)<a href="/biro-jodoh/{{ $n['slug'] }}">Biro Jodoh {{ $n['name'] }}</a>{{ !$loop->last ? ' · ' : '' }}@endforeach</p>
<h2 class="ld-h2" style="margin-top:28px">Panduan terkait</h2>
<div class="ld-grid3" style="margin-top:12px">
@foreach($topics as $slug => $t)
<div class="ld-card"><h3 style="margin:0 0 6px"><a href="/panduan/{{ $slug }}">{{ $t['title'] }}</a></h3><p class="ld-muted">{{ \Illuminate\Support\Str::limit($t['desc'], 100) }}</p></div>
@endforeach
</div>
</div></section>
@endsection
