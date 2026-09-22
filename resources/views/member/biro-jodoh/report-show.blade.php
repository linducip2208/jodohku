@extends('layouts.member')
@section('title', 'Laporan Kecocokan — Jodohku')
@section('content')
<h1 class="jk-h1">Kecocokan dengan {{ $data->candidate?->displayName() }}</h1>
<p class="jk-muted">Skor: <strong>{{ round($data->score) }}/100</strong></p>
<div class="jk-section"><div class="jk-h2">Ringkasan</div><p>{{ $data->summary }}</p></div>
<div class="jk-section"><div class="jk-h2">Kenapa cocok?</div>
<ul>@forelse(($data->breakdown['why'] ?? []) as $w)<li>{{ $w }}</li>@empty<li class="jk-muted">Belum cukup data.</li>@endforelse</ul>
</div>
<div class="jk-section"><div class="jk-h2">Sebaiknya dibicarakan</div>
<ul>@forelse(($data->breakdown['discuss'] ?? []) as $d)<li>{{ $d }}</li>@empty<li class="jk-muted">Belum ada saran.</li>@endforelse</ul>
</div>
@endsection
