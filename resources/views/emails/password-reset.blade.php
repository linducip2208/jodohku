@extends('layouts.mail')
@section('subject', 'Reset password Jodohku')
@section('content')
<h2>Reset password 🔑</h2>
<p>Klik tombol di bawah untuk membuat password baru:</p>
<p><a class="btn" href="{{ $url ?? url('/reset-password/token') }}">Reset Password</a></p>
<p style="font-size:13px;color:#71717a">Jika tidak meminta reset, abaikan email ini.</p>
@endsection
