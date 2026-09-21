@extends('layouts.mail')
@section('subject', 'Pembayaran gagal ⚠️')
@section('content')
<h2>Pembayaran belum berhasil ⚠️</h2>
<p>Pembayaran untuk <strong>{{ $item ?? 'pesananmu' }}</strong> gagal atau kedaluwarsa. Kamu bisa coba lagi:</p>
<p><a class="btn" href="{{ url('/premium') }}">Coba Lagi</a></p>
<p style="font-size:13px;color:#71717a">Dana tidak terpotong jika status gagal.</p>
@endsection
