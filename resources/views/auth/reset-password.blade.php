@extends('layouts.landing')
@section('title', 'Reset Password — Jodohku')
@section('robots', 'noindex, nofollow')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:440px"><div class="ld-card">
<h2 class="ld-h2">Buat password baru 🔒</h2>
<form method="POST" action="{{ route('password.update') }}" style="margin-top:12px">@csrf
<input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">
<input name="email" type="email" required value="{{ request('email') }}" placeholder="Email" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin-bottom:10px">
<input name="password" type="password" required placeholder="Password baru" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin-bottom:10px">
<input name="password_confirmation" type="password" required placeholder="Konfirmasi" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin-bottom:10px">
<button class="ld-btn" style="width:100%" type="submit">Reset Password</button></form>
</div></div></section>
@endsection
