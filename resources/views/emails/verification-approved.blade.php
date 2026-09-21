@extends('layouts.mail')
@section('subject', 'Verifikasi disetujui ✅')
@section('content')
<h2>Verifikasi disetujui! ✅</h2>
<p>Selamat, akunmu kini ber-badge <strong>Terverifikasi</strong>. Profilmu mendapat prioritas di Discover.</p>
<p><a class="btn" href="{{ url('/discover') }}">Lihat Discover</a></p>
@endsection
