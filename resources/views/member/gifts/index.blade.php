@extends('layouts.member')
@section('title', 'Gifts — Jodohku')
@section('content')
<h1 class="jk-h1">🎁 Gifts</h1><p class="jk-muted">Didukung GiftService + CreditService. Kirim hadiah virtual ke match.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@php $gifts = collect(); try { $gifts = app(\App\Services\GiftService::class)->catalog(); } catch (\Throwable) {} @endphp
<div class="jk-grid">
@forelse($gifts as $g)
<div class="jk-section" style="text-align:center"><div style="font-size:40px">{{ $g->emoji ?? '🎁' }}</div><strong>{{ $g->name ?? $g->code }}</strong><div class="jk-muted">{{ $g->credits ?? 10 }} kredit</div>
<form method="POST" action="/gifts/send" style="margin-top:8px">@csrf<input type="hidden" name="gift" value="{{ $g->code ?? $g->id }}"><input class="jk-input" name="receiver_id" placeholder="ID penerima" required style="width:100%;margin-bottom:6px"><button class="jk-btn jk-btn-like" type="submit">Kirim</button></form></div>
@empty
<div class="jk-section" style="text-align:center"><div style="font-size:40px">🌹</div><strong>Mawar</strong><div class="jk-muted">10 kredit</div><form method="POST" action="/gifts/send">@csrf<input type="hidden" name="gift" value="rose"><input class="jk-input" name="receiver_id" placeholder="ID penerima" required style="width:100%;margin:6px 0"><button class="jk-btn jk-btn-like" type="submit">Kirim</button></form></div>
<div class="jk-section" style="text-align:center"><div style="font-size:40px">☕</div><strong>Kopi</strong><div class="jk-muted">15 kredit</div><form method="POST" action="/gifts/send">@csrf<input type="hidden" name="gift" value="coffee"><input class="jk-input" name="receiver_id" placeholder="ID penerima" required style="width:100%;margin:6px 0"><button class="jk-btn jk-btn-like" type="submit">Kirim</button></form></div>
@endforelse
</div>
@endsection
