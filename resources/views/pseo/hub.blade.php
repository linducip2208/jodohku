@extends('layouts.landing')
@section('title', 'Biro Jodoh Indonesia — Direktori Kota & Panduan Taaruf')
@section('meta_description', 'Direktori biro jodoh modern per kota di Indonesia plus panduan taaruf: cara taaruf, persiapan nikah, dan tips pasangan serius.')
@section('content')
<section class="ld-section"><div class="ld-wrap">
<p class="ld-muted"><a href="/">Beranda</a> / Biro Jodoh</p>
<h1 class="ld-h2">Biro Jodoh Indonesia</h1>
<p class="ld-sub">Jodohku adalah biro jodoh modern: matchmaking berbasis data, alur Smart Taaruf yang terpandu, dan komunitas aman. Jelajahi berdasarkan kotamu — total {{ number_format($totalMembers) }} member aktif di seluruh Indonesia.</p>
<h2 class="ld-h2" style="margin-top:32px">Biro jodoh per kota</h2>
<div class="ld-grid3" style="margin-top:12px">
@foreach($cities as $slug => $c)
<div class="ld-card"><h3 style="margin:0 0 6px"><a href="/biro-jodoh/{{ $slug }}">Biro Jodoh {{ $c['name'] }}</a></h3>
<p class="ld-muted">{{ $c['province'] }} · {{ number_format($counts[strtolower($c['name'])] ?? 0) }} member aktif</p>
<p class="ld-muted">{{ \Illuminate\Support\Str::limit($c['intro'], 120) }}</p></div>
@endforeach
</div>
<h2 class="ld-h2" style="margin-top:32px">Panduan taaruf</h2>
<div class="ld-grid3" style="margin-top:12px">
<div class="ld-card"><h3 style="margin:0 0 6px"><a href="/taaruf">Smart Taaruf</a></h3><p class="ld-muted">Alur kenalan → taaruf → khitbah dengan wali, topik, dan konselor.</p></div>
@foreach($topics as $slug => $t)
<div class="ld-card"><h3 style="margin:0 0 6px"><a href="/panduan/{{ $slug }}">{{ $t['title'] }}</a></h3><p class="ld-muted">{{ \Illuminate\Support\Str::limit($t['desc'], 110) }}</p></div>
@endforeach
</div>
</div></section>
@endsection
