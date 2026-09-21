@extends('layouts.mail')
@section('subject', 'Kamu dapat match baru! 💘')
@section('content')
<h2>It&apos;s a Match! 🎉</h2>
<p>Kamu dan <strong>{{ $otherName ?? 'seseorang yang cocok' }}</strong> saling suka (skor {{ $score ?? '—' }}%).</p>
<p><a class="btn" href="{{ url('/chat') }}">Sapa Sekarang 💬</a></p>
<p style="font-size:13px;color:#71717a">Tips: kirim icebreaker personal, jangan sekadar “hai”.</p>
@endsection
