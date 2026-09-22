@extends('layouts.member')
@section('title', 'Taaruf Saya — Jodohku')
@section('content')
<h1 class="jk-h1">Perjalanan Taaruf</h1>
<p class="jk-muted">Kenalan → taaruf → khitbah → menikah, didampingi wali.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="jk-alert err">{{ $errors->first() }}</div>@endif
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($data as $c)
<div class="jk-section">
<div class="jk-h2">{{ $c->partner?->displayName() ?? $c->initiator?->displayName() ?? 'Pasangan' }}</div>
<div class="jk-muted">Tahap: <strong>{{ $c->stage->label() }}</strong> · Status: {{ $c->status->label() }}</div>
@if($c->guardian_name)<div class="jk-muted">Wali: {{ $c->guardian_name }}@if($c->guardian_approved_at) (restu tercatat)@endif</div>@endif
<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
<a class="jk-pill" href="/biro-jodoh/taaruf/{{ $c->id }}">Detail &amp; Perjalanan</a>
@if($c->conversation_id)<a class="jk-pill" href="/chat/{{ $c->conversation_id }}">Buka Chat</a>@endif
</div>
</div>
@empty
<div class="jk-section"><div class="jk-h2">Belum ada taaruf</div><p class="jk-muted">Mulai dari match yang saling suka, lalu ajak ke jenjang taaruf.</p><a href="/matches">Lihat Match →</a></div>
@endforelse
</div>
<div style="margin-top:12px">{{ $data->links() }}</div>
@endsection
