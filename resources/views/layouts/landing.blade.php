<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<x-seo :schemas="$seoSchemas ?? []" />
<link rel="icon" href="{{ ($brandTheme['favicon'] ?? null) ?: '/favicon.ico' }}">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="{{ $brandTheme['primary'] ?? '#f43f5e' }}">
<style>
:root{--brand-primary:{{ $brandTheme['primary'] ?? '#f43f5e' }};--brand-secondary:{{ $brandTheme['secondary'] ?? '#8b5cf6' }};}
</style>
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
@vite(['resources/css/app.css', 'resources/css/member.css', 'resources/js/app.js'])
@endif
<style>
body{font-family:'Instrument Sans',system-ui,sans-serif;margin:0;color:#18181b;background:#fff}
.ld-nav{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.9);backdrop-filter:blur(10px);border-bottom:1px solid #f1f1f4}
.ld-wrap{max-width:1120px;margin:0 auto;padding:0 20px}
.ld-nav-inner{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
.ld-logo{font-weight:800;font-size:22px;text-decoration:none;color:#18181b}.ld-logo span{background:linear-gradient(135deg,var(--brand-primary),#ec4899,var(--brand-secondary));-webkit-background-clip:text;background-clip:text;color:transparent}
.ld-links{display:none;gap:22px}.ld-links a{text-decoration:none;color:#52525b;font-size:14px;font-weight:600}
@media(min-width:900px){.ld-links{display:flex}}
.ld-btn{display:inline-block;background:linear-gradient(135deg,var(--brand-primary),#ec4899);color:#fff;font-weight:700;padding:11px 22px;border-radius:999px;text-decoration:none;font-size:14px;border:0}
.ld-btn.ghost{background:#fff;color:#18181b;border:1px solid #e4e4e7}
.ld-hero{background:linear-gradient(135deg,#fff1f2,#fce7f3 45%,#ede9fe);padding:64px 0}
.ld-hero-grid{display:grid;gap:32px}.ld-hero h1{font-size:40px;line-height:1.05;letter-spacing:-1px;margin:0 0 14px}
@media(min-width:900px){.ld-hero-grid{grid-template-columns:1.1fr .9fr}.ld-hero h1{font-size:56px}}
.ld-sub{color:#52525b;font-size:17px;line-height:1.6}
.ld-card{background:#fff;border:1px solid #f1f1f4;border-radius:20px;padding:22px;box-shadow:0 8px 30px rgba(244,63,94,.08)}
.ld-grid3{display:grid;gap:16px}.ld-grid4{display:grid;gap:16px}
@media(min-width:800px){.ld-grid3{grid-template-columns:repeat(3,1fr)}.ld-grid4{grid-template-columns:repeat(4,1fr)}}
.ld-section{padding:56px 0}.ld-section.alt{background:#fafafb}
.ld-h2{font-size:30px;letter-spacing:-.6px;margin:0 0 8px}.ld-muted{color:#71717a}
.ld-faq details{border:1px solid #e4e4e7;border-radius:14px;padding:14px 16px;margin-bottom:10px;background:#fff}
.ld-footer{background:#09090b;color:#a1a1aa;padding:48px 0;font-size:14px}.ld-footer a{color:#d4d4d8;text-decoration:none}
.ld-phones{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media(max-width:899px){.ld-phones{grid-template-columns:none;grid-auto-flow:column;grid-auto-columns:minmax(200px,72%);overflow-x:auto;padding-bottom:8px;scroll-snap-type:x mandatory}.ld-phones>*{scroll-snap-align:start}}
.ld-phone{border-radius:22px;aspect-ratio:9/16;background:linear-gradient(135deg,var(--brand-primary),var(--brand-secondary));padding:3px}
.ld-phone>div{background:#fff;border-radius:19px;height:100%;padding:14px;font-size:12px}
.ld-price{font-size:34px;font-weight:800}
</style>
</head>
<body>
<header class="ld-nav"><div class="ld-wrap ld-nav-inner">
<a class="ld-logo" href="/">@include('components.brand-logo')</a>
<nav class="ld-links">
<a href="/">Home</a><a href="/biro-jodoh">Discover</a><a href="/taaruf">Taaruf</a><a href="/panduan/cara-taaruf">Panduan</a>
</nav>
<div>
@if(auth()->check())
<a class="ld-btn ghost" href="/home">Buka Aplikasi</a>
@else
<a class="ld-btn ghost" href="{{ route('login') }}">Masuk</a>
<a class="ld-btn" href="{{ route('register') }}">Daftar Gratis</a>
@endif
</div>
</div></header>
<main>@yield('content')</main>
<footer class="ld-footer"><div class="ld-wrap">
<div class="ld-grid4">
<div><div style="color:#fff;font-weight:800;font-size:18px;margin-bottom:10px">{{ $brandTheme['name'] ?? 'Jodohku' }}</div><p>{{ ($brandTheme['tagline'] ?? 'Temukan pasangan serasi dengan matching cerdas, chat realtime, dan komunitas aman Indonesia.') }}</p></div>
<div><div style="color:#fff;font-weight:700;margin-bottom:10px">Produk</div><div><a href="/biro-jodoh">Biro Jodoh</a></div><div><a href="/taaruf">Smart Taaruf</a></div><div><a href="/panduan/cara-taaruf">Panduan</a></div><div><a href="#membership">Membership</a></div></div>
<div><div style="color:#fff;font-weight:700;margin-bottom:10px">Bantuan</div><div><a href="#faq">FAQ</a></div><div><a href="/safety">Pusat Keamanan</a></div><div><a href="/verification">Verifikasi</a></div><div><a href="/contact">Kontak</a></div></div>
<div><div style="color:#fff;font-weight:700;margin-bottom:10px">Legal</div><div><a href="/terms">Syarat &amp; Ketentuan</a></div><div><a href="/privacy">Privasi</a></div><div><a href="/guidelines">Panduan Komunitas</a></div></div>
</div>
<p style="margin-top:28px">© {{ date('Y') }} {{ $brandTheme['name'] ?? 'Jodohku' }}. Dibuat dengan aman di Indonesia.</p>
</div></footer>
</body>
</html>
