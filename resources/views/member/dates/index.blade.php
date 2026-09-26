@extends('layouts.member')
@section('title', 'Ajak Kencan — Jodohku')
@section('content')
<h1 class="jk-h1">Ajak Kencan 💘</h1>
<p class="jk-muted">Dari match → kencan nyata. Pasangan mendapat notifikasi + pengingat H-24 otomatis.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->has('date'))<div class="jk-alert">{{ $errors->first('date') }}</div>@endif
<details class="jk-section" style="margin-bottom:12px"><summary class="jk-h2" style="cursor:pointer">＋ Ajak baru</summary>
<form method="POST" action="/dates" class="jk-form" style="margin-top:10px">@csrf
<label>ID pasangan (dari match/chat)</label><input name="partner_id" type="number" min="1" required value="{{ request('partner') }}">
<label>Jadwal (min. 1 jam dari sekarang)</label><input name="scheduled_at" type="datetime-local" required>
<label>Tempat (opsional)</label><input name="place" maxlength="200" placeholder="Kopi Senja, Jakarta Selatan">
<label>Catatan (opsional)</label><textarea name="note" rows="2" maxlength="500"></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Kirim ajakan</button></form>
</details>
<div class="jk-grid" style="grid-template-columns:1fr">
@forelse($plans as $p)
<div class="jk-section">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<strong>{{ $p->proposer?->displayName() }} → {{ $p->partner?->displayName() }}</strong>
<span class="jk-pill">{{ $p->status }}</span>
<span class="jk-muted">{{ $p->scheduled_at?->format('d M Y H:i') }}{{ $p->place ? ' · '.$p->place : '' }}</span>
</div>
@if($p->note)<p class="jk-muted">{{ $p->note }}</p>@endif
<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
@if($p->status === 'proposed' && (int) $p->partner_id === (int) auth()->id())
<form method="POST" action="/dates/{{ $p->id }}/respond" style="display:inline">@csrf<input type="hidden" name="action" value="accept"><button class="jk-btn jk-btn-like" type="submit">Terima 🎉</button></form>
<form method="POST" action="/dates/{{ $p->id }}/respond" style="display:inline">@csrf<input type="hidden" name="action" value="decline"><button class="jk-btn jk-btn-pass" type="submit">Tolak sopan</button></form>
@endif
@if(! in_array($p->status, ['declined', 'cancelled']))
<form method="POST" action="/dates/{{ $p->id }}" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Batalkan</button></form>
@endif
</div>
</div>
@empty
@include('components.empty', ['icon' => 'kalender', 'title' => 'Belum ada rencana', 'hint' => 'Ajak match-mu kencan dari sini.'])
@endforelse
</div>
<div style="margin-top:16px">{{ $plans->links() }}</div>
@endsection
