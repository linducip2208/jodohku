@extends('layouts.member')
@section('title', 'Laporan Kecocokan — Jodohku')
@section('content')
<h1 class="jk-h1">Laporan Kecocokan</h1>
<p class="jk-muted">Analisis kecocokan berbasis data profil, preferensi, dan kuesioner.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="jk-alert err">{{ $errors->first() }}</div>@endif
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($data as $r)
<div class="jk-section">
<div class="jk-h2">{{ $r->candidate?->displayName() }} — {{ round($r->score) }}/100</div>
<p class="jk-muted">{{ \Illuminate\Support\Str::limit($r->summary ?? '', 160) }}</p>
<a class="jk-pill" href="/biro-jodoh/laporan/{{ $r->id }}">Buka Laporan</a>
</div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada laporan. Buat dari halaman match.</p></div>
@endforelse
</div>
<div style="margin-top:12px">{{ $data->links() }}</div>
@endsection
