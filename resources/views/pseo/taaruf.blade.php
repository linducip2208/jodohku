@extends('layouts.landing')
@section('title', 'Taaruf Online: Smart Taaruf Menuju Pernikahan')
@section('meta_description', 'Smart Taaruf Jodohku: alur kenalan → taaruf → khitbah dengan topik terpandu, wali/chaperone, konselor, dan skor kompatibilitas transparan.')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:780px">
<p class="ld-muted"><a href="/">Beranda</a> / Taaruf</p>
<h1 class="ld-h2">Taaruf Online yang Terpandu</h1>
<p class="ld-sub">Taaruf adalah perkenalan menuju pernikahan yang menjaga adab. Jodohku memandunya lewat tahap <strong>kenalan → taaruf → khitbah</strong>: topik diskusi yang terarah, persetujuan wali, chaperone pihak ketiga, dan konselor bila dibutuhkan. AI hanya asisten — keputusan tetap di tanganmu dan keluargamu.</p>
<div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap"><a class="ld-btn" href="/register">Mulai taaruf</a><a class="ld-btn ghost" href="/biro-jodoh">Cari per kota</a></div>
<h2 class="ld-h2" style="margin-top:28px">Tahapan Smart Taaruf</h2>
<div class="ld-card"><ol class="ld-muted">
<li><strong>Kenalan</strong> — match berdasarkan kompatibilitas, sapa dengan topik pembuka.</li>
<li><strong>Taaruf</strong> — diskusi visi, keluarga, keuangan; libatkan wali dan chaperone.</li>
<li><strong>Khitbah</strong> — lamaran resmi setelah wali menyetujui dan istikharah.</li>
</ol></div>
<h2 class="ld-h2" style="margin-top:28px">Panduan taaruf</h2>
<div class="ld-grid3" style="margin-top:12px">
@foreach($topics as $slug => $t)
<div class="ld-card"><h3 style="margin:0 0 6px"><a href="/panduan/{{ $slug }}">{{ $t['title'] }}</a></h3><p class="ld-muted">{{ \Illuminate\Support\Str::limit($t['desc'], 100) }}</p></div>
@endforeach
</div>
<h2 class="ld-h2" style="margin-top:28px">Pertanyaan umum</h2>
<div class="ld-faq">
@foreach($faqs as $f)
<details><summary><strong>{{ $f['q'] }}</strong></summary><p class="ld-muted">{{ $f['a'] }}</p></details>
@endforeach
</div>
<h2 class="ld-h2" style="margin-top:28px">Jelajahi per kota</h2>
<p class="ld-muted">@foreach($cities as $slug => $c)<a href="/biro-jodoh/{{ $slug }}">{{ $c['name'] }}</a>{{ !$loop->last ? ' · ' : '' }}@endforeach · <a href="/biro-jodoh">semua kota</a></p>
</div></section>
@endsection
