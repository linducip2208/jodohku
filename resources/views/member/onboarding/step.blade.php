@extends('layouts.member')
@section('title', 'Lengkapi Profil — Jodohku')
@section('content')
@php
$idx = array_search($step, $steps);
$labels = ['dasar' => 'Data dasar', 'tujuan' => 'Tujuan & minat', 'foto' => 'Foto', 'preferensi' => 'Preferensi'];
@endphp
<div style="display:flex;gap:6px;margin-bottom:14px" role="list" aria-label="Langkah onboarding">
@foreach($steps as $i => $s)
<div role="listitem" style="flex:1;height:6px;border-radius:3px;background:{{ $i <= $idx ? '#f43f5e' : '#e4e4e7' }}" aria-label="Langkah {{ $i + 1 }}: {{ $labels[$s] }}{{ $i <= $idx ? ' (selesai/aktif)' : '' }}"></div>
@endforeach
</div>
<h1 class="jk-h1">Langkah {{ $idx + 1 }} dari {{ count($steps) }}: {{ $labels[$step] }}</h1>
<p class="jk-muted">Profil lengkap → rekomendasi lebih tepat. Bisa dilewati, bisa dilengkapi nanti.</p>

@if($step === 'dasar')
<div class="jk-section jk-form">
<form method="POST" action="/onboarding/dasar">@csrf
<label>Nama panggilan</label><input name="display_name" required maxlength="60" value="{{ old('display_name', $user->display_name) }}">
<label>Tanggal lahir (17+)</label><input name="date_of_birth" type="date" required value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}">
<label>Gender</label><select name="gender"><option value="female" @selected(old('gender', $user->gender?->value ?? (string) $user->gender) === 'female')>Perempuan</option><option value="male" @selected(old('gender', $user->gender?->value ?? (string) $user->gender) === 'male')>Laki-laki</option></select>
<label>Kota</label><input name="city" maxlength="120" value="{{ old('city', $user->city) }}" placeholder="Jakarta">
<button class="jk-submit" style="margin-top:12px" type="submit">Lanjut →</button></form>
</div>
@elseif($step === 'tujuan')
<div class="jk-section jk-form">
<form method="POST" action="/onboarding/tujuan">@csrf
<label>Tujuan hubungan</label>
<select name="relationship_goal">
<option value="">— Pilih —</option>
@foreach(['marriage' => 'Menikah', 'serious_relationship' => 'Serius', 'dating' => 'Pacaran', 'friendship' => 'Pertemanan'] as $v => $l)
<option value="{{ $v }}" @selected(old('relationship_goal', $user->profile?->relationship_goal?->value ?? (string) ($user->profile?->relationship_goal ?? '')) === $v)>{{ $l }}</option>
@endforeach
</select>
<label>Minat (maks 10)</label>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px" role="group" aria-label="Minat">
@php $mine = $user->interests->pluck('id')->all(); @endphp
@foreach($interests as $in)
<label class="jk-pill" style="cursor:pointer"><input type="checkbox" name="interests[]" value="{{ $in->id }}" @checked(in_array($in->id, old('interests', $mine)))> {{ $in->name }}</label>
@endforeach
</div>
<button class="jk-submit" style="margin-top:12px" type="submit">Lanjut →</button></form>
</div>
@elseif($step === 'foto')
<div class="jk-section"><div class="jk-h2">Foto profil</div>
<p class="jk-muted">Profil berfoto mendapat 5× lebih banyak like. Foto dimoderasi dulu (maks 8MB).</p>
@php $count = $user->photos()->count(); @endphp
@if($count > 0)<p class="jk-muted">Sudah ada {{ $count }} foto. Bagus!</p>@endif
<form method="POST" action="{{ route('member.profile.photos') }}" enctype="multipart/form-data" class="jk-form" style="margin-top:10px">@csrf
<input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple aria-label="Upload foto">
<button class="jk-submit" style="margin-top:10px" type="submit">Upload</button></form>
<div style="display:flex;gap:8px;margin-top:12px">
<a class="jk-btn jk-btn-like" style="flex:1;text-decoration:none;text-align:center" href="/onboarding/preferensi">Lanjut →</a>
</div></div>
@else
<div class="jk-section jk-form">
@php $pref = $user->partnerPreference; @endphp
<form method="POST" action="/onboarding/preferensi">@csrf
<label>Tertarik pada</label><select name="gender_preference"><option value="">Semua</option><option value="female" @selected(old('gender_preference', $pref?->gender_preference?->value ?? (string) ($pref?->gender_preference ?? '')) === 'female')>Perempuan</option><option value="male" @selected(old('gender_preference', $pref?->gender_preference?->value ?? (string) ($pref?->gender_preference ?? '')) === 'male')>Laki-laki</option></select>
<label>Rentang usia</label>
<div style="display:flex;gap:8px;align-items:center"><input name="min_age" type="number" min="17" max="80" value="{{ old('min_age', $pref?->min_age ?? 20) }}" aria-label="Umur minimal"><span>–</span><input name="max_age" type="number" min="17" max="80" value="{{ old('max_age', $pref?->max_age ?? 35) }}" aria-label="Umur maksimal"></div>
<label>Jarak maks (km)</label><input name="max_distance_km" type="number" min="1" max="20000" value="{{ old('max_distance_km', $pref?->max_distance_km ?? 100) }}">
<button class="jk-submit" style="margin-top:12px" type="submit">Selesai — ke Discover →</button></form>
</div>
@endif
@if($step !== 'dasar')
<div style="margin-top:10px"><a class="jk-muted" href="/onboarding/{{ $steps[$idx - 1] }}">← Kembali</a></div>
@endif
@endsection
