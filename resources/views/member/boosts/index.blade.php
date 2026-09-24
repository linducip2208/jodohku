@extends('layouts.member')
@section('title', 'Boost — Jodohku')
@section('content')
<h1 class="jk-h1">Boost</h1><p class="jk-muted">Naik ke puncak Discover 30 menit. Boost hanya memengaruhi urutan tampil, bukan skor.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@php $live = false; try { $live = auth()->check() ? app(\App\Services\BoostService::class)->isLive(auth()->user()) : false; } catch (\Throwable) {} @endphp
<div class="jk-hero"><div class="jk-h2">{{ $live ? 'Boost AKTIF — profilmu sedang di puncak!' : 'Aktifkan Boost sekarang' }}</div>
@if(!$live)<form method="POST" action="/boosts/activate" style="margin-top:10px">@csrf<button class="jk-submit" type="submit">Aktifkan Boost (30 menit)</button></form>@endif
</div>
@endsection
