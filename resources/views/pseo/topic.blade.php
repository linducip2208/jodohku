@extends('layouts.landing')
@section('title', $topic['title'])
@section('meta_description', $topic['desc'])
@section('content')
<article class="ld-section"><div class="ld-wrap" style="max-width:780px">
<p class="ld-muted"><a href="/">Beranda</a> / <a href="/taaruf">Taaruf</a> / Panduan</p>
<h1 class="ld-h2">{{ $topic['title'] }}</h1>
<p class="ld-sub">{{ $topic['intro'] }}</p>
<div class="ld-card" style="margin-top:16px">
<h3>Poin penting</h3>
<ul class="ld-muted">
@foreach($topic['points'] as $p)<li>{{ $p }}</li>@endforeach
</ul>
<div style="margin-top:12px"><a class="ld-btn" href="/register">Terapkan di Jodohku — gratis</a></div>
</div>
<h2 class="ld-h2" style="margin-top:28px">Pertanyaan umum</h2>
<div class="ld-faq">
@foreach($topic['faqs'] as $f)
<details><summary><strong>{{ $f['q'] }}</strong></summary><p class="ld-muted">{{ $f['a'] }}</p></details>
@endforeach
</div>
<h2 class="ld-h2" style="margin-top:28px">Panduan lain</h2>
<div class="ld-grid3" style="margin-top:12px">
@foreach($others as $slug => $t)
<div class="ld-card"><h3 style="margin:0 0 6px"><a href="/panduan/{{ $slug }}">{{ $t['title'] }}</a></h3><p class="ld-muted">{{ \Illuminate\Support\Str::limit($t['desc'], 100) }}</p></div>
@endforeach
</div>
<p class="ld-muted" style="margin-top:20px">Terkait kota: @foreach($cities as $slug => $c)<a href="/biro-jodoh/{{ $slug }}">{{ $c['name'] }}</a>{{ !$loop->last ? ' · ' : '' }}@endforeach</p>
</div></article>
@endsection
