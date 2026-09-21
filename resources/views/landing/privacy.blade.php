@extends('layouts.landing')
@section('title', 'Kebijakan Privasi — Jodohku')
@section('meta_description', 'Kebijakan privasi Jodohku: data yang kami kumpulkan, cara penggunaan, dan hak kamu atas datamu.')
@section('content')
<section class="ld-section"><div class="ld-wrap" style="max-width:760px">
<h1 class="ld-h2">Kebijakan Privasi</h1>
<p class="ld-muted">Terakhir diperbarui: {{ date('d M Y') }}</p>
<div class="ld-card" style="margin-top:16px">
<h3>1. Data yang kami kumpulkan</h3>
<p class="ld-muted">Nama, email, nomor telepon (opsional), tanggal lahir, kota, foto profil, preferensi pasangan, jawaban kuesioner, dan isi pesan yang kamu kirim melalui platform.</p>
<h3>2. Penggunaan data</h3>
<p class="ld-muted">Data digunakan untuk matching, chat, verifikasi, pencegahan penipuan, dan peningkatan layanan. Kami tidak menjual data pribadimu.</p>
<h3>3. Visibilitas profil</h3>
<p class="ld-muted">Kamu mengatur sendiri apa yang tampil: umur, lokasi, status online, dan siapa yang boleh menghubungimu — lewat Pengaturan › Privasi. Profil privat tidak tampil di pencarian.</p>
<h3>4. Foto privat &amp; moderasi</h3>
<p class="ld-muted">Foto privat hanya tampil sesuai pengaturan visibilitas. Semua foto melewati moderasi sebelum tampil publik.</p>
<h3>5. Penyimpanan &amp; keamanan</h3>
<p class="ld-muted">Password di-hash, kredensial gateway terenkripsi, dan akses admin tercatat di audit log. Kamu dapat meminta ekspor atau penghapusan akun kapan pun via Pengaturan.</p>
<h3>6. Kontak</h3>
<p class="ld-muted">Pertanyaan privasi: <a href="/contact">hubungi kami</a>.</p>
</div>
</div></section>
@endsection
