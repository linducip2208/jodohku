@extends('layouts.mail')
@section('subject', 'Premium aktif ⭐')
@section('content')
<h2>Premium aktif! ⭐</h2>
<p>Paket <strong>{{ $planName ?? 'Premium' }}</strong> sudah aktif sampai {{ $endsAt ?? '—' }}.</p>
<p>Nikmati: like tanpa batas, lihat siapa yang like, filter lanjutan, rewind, dan incognito.</p>
<p><a class="btn" href="{{ url('/discover') }}">Jelajahi Premium Discover</a></p>
@endsection
