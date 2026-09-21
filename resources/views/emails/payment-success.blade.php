@extends('layouts.mail')
@section('subject', 'Pembayaran berhasil ✅')
@section('content')
<h2>Pembayaran berhasil ✅</h2>
<p>Terima kasih! Pembayaran <strong>{{ $amount ?? '' }}</strong> untuk <strong>{{ $item ?? 'Jodohku' }}</strong> telah diterima.</p>
<p><a class="btn" href="{{ url('/credits') }}">Lihat Dompet</a></p>
@endsection
