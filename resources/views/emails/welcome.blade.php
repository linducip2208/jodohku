@extends('layouts.mail')
@section('subject', 'Selamat datang di Jodohku 💖')
@section('content')
<h2>Hai {{ $user->displayName() ?? $user->name ?? 'kamu' }}! 🎉</h2>
<p>Akun Jodohkumu sudah aktif. Lengkapi profil untuk dapat rekomendasi terbaik:</p>
<ol><li>Tambahkan 3+ foto terbaik</li><li>Isi bio &amp; minat</li><li>Jawab kuesioner kepribadian</li></ol>
<p><a class="btn" href="{{ url('/discover') }}">Mulai Discover →</a></p>
<p style="font-size:13px;color:#71717a">Skor kompatibilitasmu dihitung otomatis oleh MatchingEngine.</p>
@endsection
