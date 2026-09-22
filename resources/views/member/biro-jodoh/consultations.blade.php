@extends('layouts.member')
@section('title', 'Konsultasiku — Jodohku')
@section('content')
<h1 class="jk-h1">Konsultasiku</h1>
<p class="jk-muted">Jadwal sesi dengan konselor biro jodoh.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="jk-alert err">{{ $errors->first() }}</div>@endif
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($data as $c)
<div class="jk-section">
<div class="jk-h2">{{ $c->topic }}</div>
<div class="jk-muted">{{ $c->counselor?->user?->displayName() }} · {{ $c->scheduled_at?->format('d M Y H:i') }} · {{ $c->status->label() }}</div>
@if($c->notes)<p class="jk-muted">{{ $c->notes }}</p>@endif
<div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
<form method="POST" action="/biro-jodoh/konsultasi/{{ $c->id }}/batal">@csrf<button class="jk-pill" type="submit">Batalkan</button></form>
</div>
</div>
@empty
<div class="jk-section"><p class="jk-muted">Belum ada konsultasi. <a href="/biro-jodoh/konselor">Booking sekarang →</a></p></div>
@endforelse
</div>
<div style="margin-top:12px">{{ $data->links() }}</div>
@endsection
