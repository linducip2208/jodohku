@extends('layouts.mail')
@section('subject', 'Pesan baru dari ' . ($senderName ?? 'match-mu'))
@section('content')
<h2>Pesan baru 💬</h2>
<p><strong>{{ $senderName ?? 'Match-mu' }}</strong> mengirim pesan:</p>
<blockquote style="background:#f4f4f5;border-radius:10px;padding:12px">{{ $excerpt ?? 'Buka aplikasi untuk membaca pesan lengkap.' }}</blockquote>
<p><a class="btn" href="{{ url('/chat') }}">Balas Pesan</a></p>
@endsection
