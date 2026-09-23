@extends('layouts.member')
@section('title', 'Gifts — Jodohku')
@section('content')
<h1 class="jk-h1">Gifts</h1><p class="jk-muted">Didukung GiftService + CreditService. Kirim hadiah virtual ke match.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@php $gifts = collect(); try { $gifts = app(\App\Services\GiftService::class)->catalog(); } catch (\Throwable) {} @endphp
<div class="jk-grid">
@forelse($gifts as $g)
<div class="jk-section" style="text-align:center"><div style="font-size:15px;font-weight:800">{{ $g->name ?? $g->code }}</div><div class="jk-muted">{{ number_format($g->credit_price ?? 0, 0, ',', '.') }} kredit</div>
<form method="POST" action="/gifts/send" style="margin-top:8px">@csrf<input type="hidden" name="gift" value="{{ $g->code ?? $g->id }}"><input class="jk-input" name="receiver_id" placeholder="ID penerima" required style="width:100%;margin-bottom:6px"><button class="jk-btn jk-btn-like" type="submit">Kirim</button></form></div>
@empty
@include('components.empty', ['icon' => 'bintang', 'title' => 'Belum ada gift', 'hint' => 'Katalog gift akan muncul di sini.'])
@endforelse
</div>
@endsection
