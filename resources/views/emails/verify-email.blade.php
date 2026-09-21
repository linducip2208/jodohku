@extends('layouts.mail')
@section('subject', 'Verifikasi email Jodohku')
@section('content')
<h2>Verifikasi emailmu 📧</h2>
<p>Klik tombol di bawah untuk memverifikasi alamat email akun Jodohkumu:</p>
<p><a class="btn" href="{{ $url ?? url('/verify-email') }}">Verifikasi Email</a></p>
<p style="font-size:13px;color:#71717a">Link berlaku 60 menit. Jika tidak merasa mendaftar, abaikan email ini.</p>
@endsection
