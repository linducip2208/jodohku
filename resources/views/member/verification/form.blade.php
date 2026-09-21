@extends('layouts.member')
@section('title', 'Verifikasi — Jodohku')
@section('content')
<h1 class="jk-h1">✅ Verifikasi</h1><p class="jk-muted">Didukung VerificationService (foto/dokumen/selfie). Status: <strong>{{ auth()->user()?->is_verified ? 'Terverifikasi ✔' : 'Belum terverifikasi' }}</strong></p>
@php $reqs = auth()->check() ? auth()->user()->verificationRequests()->latest('id')->take(5)->get() : collect(); @endphp
<div class="jk-section"><div class="jk-h2">Ajukan verifikasi</div>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<form method="POST" action="/verification" enctype="multipart/form-data" class="jk-form">@csrf
<label>Jenis verifikasi</label><select name="type"><option value="photo">Foto + selfie</option><option value="id_card">KTP/Identitas</option><option value="video">Video singkat</option></select>
<label>Catatan (opsional)</label><textarea name="notes" rows="2" placeholder="Contoh: nama sesuai KTP..."></textarea>
<button class="jk-submit" style="margin-top:12px" type="submit">Kirim Pengajuan →</button></form>
</div>
<div class="jk-section"><div class="jk-h2">Riwayat pengajuan</div><table class="jk-table"><thead><tr><th>Waktu</th><th>Tipe</th><th>Status</th></tr></thead><tbody>@forelse($reqs as $r)<tr><td>{{ $r->created_at?->format('d M Y') }}</td><td>{{ $r->type }}</td><td>{{ $r->status }}</td></tr>@empty<tr><td colspan="3" class="jk-muted">Belum ada pengajuan.</td></tr>@endforelse</tbody></table></div>
@endsection
