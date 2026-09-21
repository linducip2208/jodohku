@extends('layouts.mail')
@section('subject', 'Permintaan chat baru 📨')
@section('content')
<h2>Ada yang ingin kenalan 📨</h2>
<p><strong>{{ $senderName ?? 'Seseorang' }}</strong> mengirimu permintaan chat. Terima untuk mulai mengobrol.</p>
<p><a class="btn" href="{{ url('/chat') }}">Lihat Permintaan</a></p>
@endsection
