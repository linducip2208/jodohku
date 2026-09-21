@extends('layouts.mail')
@section('subject', 'Peringatan akun Jodohku')
@section('content')
<h2>Peringatan akun ⚠️</h2>
<p>{{ $reason ?? 'Aktivitas di akunmu melanggar panduan komunitas.' }} Mohon patuhi aturan agar akun tidak dibatasi.</p>
<p><a class="btn" href="{{ url('/safety') }}">Baca Panduan Keamanan</a></p>
@endsection
