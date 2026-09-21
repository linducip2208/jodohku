@extends('layouts.landing')
@section('title', 'Hubungi Kami — Jodohku')
@section('meta_description', 'Hubungi tim Jodohku untuk bantuan akun, banding moderasi, atau kerja sama.')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:640px">
<h1 class="ld-h2">Hubungi Kami</h1>
<p class="ld-muted">Butuh bantuan? Isi formulir — pesanmu tercatat sebagai tiket bantuan.</p>
@if(session('status'))<div class="ld-card" style="border-color:#bbf7d0;background:#f0fdf4;margin-top:12px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="ld-card" style="border-color:#fecaca;background:#fef2f2;margin-top:12px">{{ $errors->first() }}</div>@endif
<div class="ld-card" style="margin-top:16px">
<form method="POST" action="/contact">
@csrf
<label style="font-size:13px;font-weight:700">Nama</label><input name="name" required value="{{ old('name') }}" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Email</label><input name="email" type="email" required value="{{ old('email') }}" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<label style="font-size:13px;font-weight:700">Topik</label>
<select name="topic" style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">
<option value="akun">Akun &amp; login</option><option value="pembayaran">Pembayaran &amp; premium</option><option value="moderasi">Moderasi &amp; banding</option><option value="keamanan">Keamanan &amp; laporan</option><option value="kerjasama">Kerja sama</option><option value="lainnya">Lainnya</option>
</select>
<label style="font-size:13px;font-weight:700">Pesan</label><textarea name="message" rows="5" required style="width:100%;border:1px solid #e4e4e7;border-radius:10px;padding:10px;margin:6px 0 12px">{{ old('message') }}</textarea>
<button class="ld-btn" style="width:100%" type="submit">Kirim Pesan</button>
</form>
</div>
</div></section>
@endsection
