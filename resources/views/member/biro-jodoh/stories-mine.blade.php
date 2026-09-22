@extends('layouts.member')
@section('title', 'Kisahku — Jodohku')
@section('content')
<h1 class="jk-h1">Kisahku</h1>
<p class="jk-muted">Status pengajuan kisah suksesmu.</p>
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($data as $s)
<div class="jk-section">
<div class="jk-h2">{{ $s->partner_name }} — {{ $s->status->label() }}</div>
<p>{{ \Illuminate\Support\Str::limit($s->story, 200) }}</p>
</div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada. <a href="/biro-jodoh/kisah">Kirim kisah →</a></p></div>
@endforelse
</div>
<div style="margin-top:12px">{{ $data->links() }}</div>
@endsection
