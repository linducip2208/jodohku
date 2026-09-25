@extends('layouts.member')
@section('title', 'Blokir Kontak — Jodohku')
@section('content')
<h1 class="jk-h1">Blokir kontak</h1>
<p class="jk-muted">Cegah orang dari kontak HP-mu muncul di discovery — tanpa mengunggah buku kontak.</p>
<div class="jk-section" aria-label="Cara kerja">
<div class="jk-h2">Privasi dulu</div>
<ul class="jk-muted" style="margin:0;padding-left:18px;font-size:13px">
<li>Nomor dinormalisasi lalu di-hash (HMAC-SHA256) di perangkat/server.</li>
<li>Nomor mentah <strong>tidak pernah disimpan</strong> di mana pun.</li>
<li>Kecocokan langsung menjadi blokir biasa (bisa dibatalkan per akun).</li>
<li>Hapus data kapan saja di bawah — hash dan blokir kontak ikut terhapus.</li>
</ul>
</div>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section"><div class="jk-h2">Status</div>
<p class="jk-muted" style="margin:0">{{ $status['hashes'] }} hash tersimpan · {{ $status['matched'] }} akun terblokir via kontak.</p></div>
<div class="jk-section jk-form"><div class="jk-h2">Tambah nomor</div>
<form method="POST" action="/kontak-blokir">@csrf
<label for="contact-phones">Satu nomor per baris (contoh 0812… atau +62…)</label>
<textarea id="contact-phones" name="phones" rows="5" maxlength="20000" placeholder="081234567890&#10;+6281299998888"></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Proses</button></form>
</div>
<div class="jk-section"><div class="jk-h2">Nonaktifkan</div>
<p class="jk-muted">Menghapus semua hash + membatalkan blokir kontak.</p>
<form method="POST" action="/kontak-blokir">@csrf @method('DELETE')<button class="jk-btn jk-btn-pass" type="submit">Hapus semua data kontak</button></form>
</div>
@endsection
