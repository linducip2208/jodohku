@extends('layouts.member')
@section('title', ($profileUser->displayName() ?? 'Profil') . ' — Jodohku')
@section('content')
@php
$why = null;
try {
  $me = auth()->user();
  if ($me && $me->id !== $profileUser->id) { $why = app(\App\Services\MatchExplanation::class)->for($me, $profileUser); }
} catch (\Throwable) {}
$photos = $profileUser->photos ?? collect();
$isSelf = auth()->id() === $profileUser->id;
$profile = $profileUser->profile;
@endphp
<a href="/discover" class="jk-muted">← Kembali ke Discover</a>
<div class="jk-card" style="margin-top:8px">
<div class="jk-photo" style="aspect-ratio:4/3">
@if($profileUser->avatarUrl())<img src="{{ $profileUser->avatarUrl() }}" alt="Foto {{ $profileUser->displayName() }}">@else<div class="jk-photo-fallback">{{ strtoupper(substr((string)($profileUser->displayName() ?? '?'),0,1)) }}</div>@endif
<span class="{{ $profileUser->is_online ? 'jk-online' : 'jk-offline' }}" title="{{ $profileUser->is_online ? 'Online' : 'Offline' }}"></span>
@if(isset($score))<span class="jk-compat">{{ (int) $score }}% cocok</span>@endif
</div>
<div class="jk-card-body">
<div class="jk-name" style="font-size:22px">{{ $profileUser->displayName() }}{{ $profileUser->age() ? ', ' . $profileUser->age() : '' }} @if($profileUser->is_verified)<span class="jk-badge-verified" title="Terverifikasi" aria-label="Terverifikasi"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8 12.5l2.7 2.7L16.5 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>@endif</div>
<div class="jk-muted">{{ $profileUser->city ?? 'Indonesia' }} · {{ $profileUser->is_online ? 'Online sekarang' : 'Terakhir aktif ' . ($profileUser->last_active_at?->diffForHumans() ?? '—') }}</div>
<div class="jk-tags" style="margin-top:8px" aria-label="Status verifikasi">
@if($profileUser->email_verified_at)<span class="jk-pill verified" title="Email terverifikasi">Email ✓</span>@endif
@if($profileUser->phone_verified_at)<span class="jk-pill verified" title="Nomor HP terverifikasi">HP ✓</span>@endif
@if($profileUser->is_verified)<span class="jk-pill verified" title="Identitas terverifikasi oleh tim Jodohku">Identitas ✓</span>@endif
</div>
@if($profile?->headline)<p style="margin:10px 0 0"><strong>{{ $profile->headline }}</strong></p>@endif
@if($profile?->occupation)<p class="jk-muted" style="margin:4px 0 0">{{ $profile->occupation }}{{ $profile?->education ? ' · '.$profile->education : '' }}</p>@endif
@if(! $isSelf)
<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap" x-data="{ sending:false }">
<button class="jk-btn jk-btn-like" style="flex:1;min-width:140px" :disabled="sending" @click="sending = true; fetch('/chat/create', { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json' }, body: JSON.stringify({ user_id: {{ (int) $profileUser->id }} }) }).then(r => r.json()).then(j => { if (j.conversation_id) window.location.href = '/chat/' + j.conversation_id; else { window.jkToast('Gagal membuat percakapan', false); sending = false; } }).catch(() => { window.jkToast('Gagal membuat percakapan', false); sending = false; })">Kirim pesan</button>
<a class="jk-btn jk-btn-pass" style="text-decoration:none;text-align:center;flex:1;min-width:140px" href="/biro-jodoh/laporan">Kompatibilitas</a>
</div>
@livewire('like-buttons', ['userId' => $profileUser->id], key('profile-like-' . $profileUser->id))
@else
<div style="margin-top:12px"><a class="jk-btn jk-btn-pass" style="text-decoration:none;text-align:center" href="/profile/edit">Edit profil</a></div>
@endif
</div>
</div>

@php
$bioVisible = $isSelf;
if (! $bioVisible) {
    $bv = $profileUser->profilePrivacy?->bio_visibility;
    $bv = $bv?->value ?? (string) ($bv ?? 'public');
    $bioVisible = $bv === 'public' || auth()->check() && $bv === 'members_only' || (auth()->user()?->isStaff() ?? false);
}
@endphp
@if($profile?->bio && $bioVisible)
<section class="jk-section" aria-labelledby="p-about"><h2 class="jk-h2" id="p-about">Tentang</h2><p style="margin:0">{{ $profile->bio }}</p></section>
@endif

<section class="jk-section" aria-labelledby="p-values"><h2 class="jk-h2" id="p-values">Nilai & gaya hidup</h2>
<div class="jk-tags">
@if($profile?->religion)<span class="jk-tag">{{ $profile->religion }}</span>@endif
@if($profile?->marital_status)<span class="jk-tag">{{ $profile->marital_status }}</span>@endif
@if($profile?->relationship_goal)<span class="jk-tag">Tujuan: {{ ucwords(str_replace(['_', '-'], ' ', (string)($profile->relationship_goal instanceof \BackedEnum ? $profile->relationship_goal->value : $profile->relationship_goal))) }}</span>@endif
@if($profile?->height_cm)<span class="jk-tag">{{ $profile->height_cm }} cm</span>@endif
@if($profile?->occupation)<span class="jk-tag">{{ $profile->occupation }}</span>@endif
@if($profile?->education)<span class="jk-tag">{{ $profile->education }}</span>@endif
</div>
@if(($profileUser->interests ?? collect())->isNotEmpty())
<h3 style="font-size:14px;margin:12px 0 6px">Minat</h3>
<div class="jk-tags">@foreach($profileUser->interests->take(10) as $in)<span class="jk-tag">{{ $in->name }}</span>@endforeach</div>
@endif
</section>

@include('components.why-match', ['why' => $why ?? null])
@if(!empty($why))
<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"><a class="jk-pill" href="/biro-jodoh/laporan">Lihat laporan kompatibilitas</a><a class="jk-pill" href="/biro-jodoh/taaruf">Topik taaruf</a></div>
@endif

@if($photos->count())
<section class="jk-section" aria-labelledby="p-photos"><h2 class="jk-h2" id="p-photos">Foto ({{ $photos->count() }})</h2>
<div class="jk-grid" style="grid-template-columns:repeat(3,1fr)">@foreach($photos as $ph)<div class="jk-card"><div class="jk-photo" style="aspect-ratio:1/1">@if(!empty($ph->path))<img src="{{ asset('storage/'.$ph->path) }}" alt="Foto {{ $profileUser->displayName() }}" loading="lazy">@elseif(!empty($ph->url))<img src="{{ $ph->url }}" alt="Foto {{ $profileUser->displayName() }}" loading="lazy">@else<div class="jk-photo-fallback">Foto</div>@endif</div></div>@endforeach</div>
</section>
@endif

@if(! $isSelf)
<section class="jk-section" aria-labelledby="p-safe"><h2 class="jk-h2" id="p-safe">Keamanan</h2>
<p class="jk-muted">Jaga privasi. Jangan bagikan OTP, password, atau data bank.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
<form method="POST" action="/safety/block" onsubmit="return confirm('Blokir member ini?')">@csrf<input type="hidden" name="user_id" value="{{ $profileUser->id }}"><button class="jk-pill" type="submit">Blokir</button></form>
<form method="POST" action="/safety/report">@csrf<input type="hidden" name="user_id" value="{{ $profileUser->id }}"><input type="hidden" name="reason" value="Lainnya"><button class="jk-pill" type="submit">Laporkan</button></form>
<a class="jk-pill" href="/safety">Pusat Keamanan</a>
</div>
</section>
@endif
@endsection
