@extends('layouts.member')
@section('title', 'Edit Profil — Jodohku')
@section('content')
<h1 class="jk-h1">✏️ Edit Profil</h1>
<p class="jk-muted">Foto baru masuk moderasi (maks 8MB, JPG/PNG/WebP) lalu tampil publik setelah disetujui.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@php $myPhotos = auth()->user()->photos()->ordered()->get(); @endphp
<div class="jk-section"><div class="jk-h2">📷 Foto saya ({{ $myPhotos->count() }}/9)</div>
<div class="jk-grid" style="grid-template-columns:repeat(3,1fr)">
@foreach($myPhotos as $ph)
<div class="jk-card"><div class="jk-photo" style="aspect-ratio:1/1">
@if($ph->path)<img src="{{ asset('storage/'.($ph->thumbnail_path ?: $ph->path)) }}" alt="" loading="lazy">@else<div class="jk-photo-fallback">📷</div>@endif
</div>
<div style="padding:6px;display:flex;gap:4px;align-items:center;justify-content:space-between">
<span class="jk-pill {{ $ph->status === 'approved' ? 'verified' : '' }}">{{ $ph->status }}</span>
<form method="POST" action="{{ route('member.profile.photos.destroy', $ph->id) }}" id="del-photo-{{ $ph->id }}">@csrf @method('DELETE')<button type="button" class="jk-btn jk-btn-pass" style="padding:4px 10px" onclick="jkConfirm('Hapus foto?', 'Foto dihapus permanen.', function () { document.getElementById('del-photo-{{ $ph->id }}').submit(); })">🗑</button></form>
</div></div>
@endforeach
</div>
<form method="POST" action="{{ route('member.profile.photos') }}" enctype="multipart/form-data" style="margin-top:12px" class="jk-form">
@csrf
<label>Tambah foto (bisa pilih beberapa)</label>
<input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
<label><input type="checkbox" name="is_private" value="1"> Tandai sebagai foto privat</label>
<button class="jk-submit" style="margin-top:10px" type="submit">Upload</button>
</form>
</div>
<div class="jk-section jk-form"><div class="jk-h2">Data dasar</div>
<form method="POST" action="/settings/profile">@csrf
<label>Nama tampilan</label><input name="display_name" value="{{ $user->display_name ?? auth()->user()->display_name }}">
<label>Kota</label><input name="city" value="{{ auth()->user()->city }}">
<button class="jk-submit" style="margin-top:12px" type="submit">Simpan</button></form>
</div>
@endsection
