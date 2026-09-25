<div
  x-data="{ sx: 0, dx: 0, dragging: false }"
  @keydown.window.left="$wire.pass()"
  @keydown.window.right="$wire.like()"
>
<div wire:loading aria-live="polite" aria-label="Memuat kandidat">
<div class="jk-skeleton" style="height:420px"></div>
</div>
<div wire:loading.remove>
@php
$name = $cand->displayName() ?? 'Member';
$age = $cand->age();
$img = $images[$photoIndex] ?? null;
$src = $img ? asset('storage/'.($img->thumbnail_path ?: $img->path)) : $cand->avatarUrl();
$dist = $distance;
@endphp
<div
  wire:key="swipe-{{ $cand->id }}"
  x-bind:style="dragging ? `transform:translateX(${dx}px) rotate(${dx / 18}deg)` : ''"
  style="transition:transform .18s ease;touch-action:pan-y"
  @pointerdown="dragging = true; sx = $event.clientX; dx = 0"
  @pointermove="if (dragging) { dx = $event.clientX - sx; }"
  @pointerup="dragging = false; if (dx > 90) { $wire.like(); } else if (dx < -90) { $wire.pass(); } dx = 0;"
  @pointercancel="dragging = false; dx = 0;"
  role="group" aria-label="Kartu {{ $name }}" aria-roledescription="kartu geser"
>
<div class="jk-card" style="overflow:hidden;padding:0;max-width:420px;margin:0 auto">
<div class="jk-photo" style="aspect-ratio:3/4;position:relative">
@if($src)
<img src="{{ $src }}" alt="Foto {{ $name }}" style="width:100%;height:100%;object-fit:cover;display:block" draggable="false">
@else
<div class="jk-photo-fallback">{{ strtoupper(substr((string) $name, 0, 1)) }}</div>
@endif
@if(count($images) > 1)
<button type="button" wire:click="photoPrev" style="position:absolute;left:8px;top:40%;background:rgba(0,0,0,.45);color:#fff;border:0;border-radius:999px;width:34px;height:34px" aria-label="Foto sebelumnya">‹</button>
<button type="button" wire:click="photoNext" style="position:absolute;right:8px;top:40%;background:rgba(0,0,0,.45);color:#fff;border:0;border-radius:999px;width:34px;height:34px" aria-label="Foto berikutnya">›</button>
<div style="position:absolute;top:8px;left:0;right:0;display:flex;gap:4px;padding:0 10px" aria-hidden="true">
@foreach($images as $i => $im)
<div style="flex:1;height:3px;border-radius:2px;background:{{ $i <= $photoIndex ? '#fff' : 'rgba(255,255,255,.45)' }}"></div>
@endforeach
</div>
@endif
@if(! empty($cand->compatibility_score))
<span class="jk-compat">{{ (int) $cand->compatibility_score }}% cocok</span>
@endif
<span class="{{ $cand->is_online ? 'jk-online' : 'jk-offline' }}" title="{{ $cand->is_online ? 'Online' : 'Offline' }}"></span>
</div>
<div class="jk-card-body">
<div class="jk-name" style="font-size:20px"><a href="/profile/{{ $cand->id }}" style="color:inherit;text-decoration:none">{{ $name }}</a>{{ $age ? ', '.$age : '' }}
@if($cand->is_verified)
<span class="jk-badge-verified" title="Terverifikasi" aria-label="Terverifikasi"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8 12.5l2.7 2.7L16.5 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
@endif
</div>
<div class="jk-muted">{{ $cand->city ?? 'Indonesia' }}@if($dist !== null) · {{ $dist }} km @endif· {{ $cand->is_online ? 'Online sekarang' : 'Terakhir aktif' }}</div>
@if($cand->profile?->headline)
<p style="margin:8px 0 0;font-size:14px">{{ \Illuminate\Support\Str::limit($cand->profile->headline, 90) }}</p>
@endif
</div>
</div>
</div>
<div class="jk-actions" style="max-width:420px;margin:12px auto 0" role="group" aria-label="Aksi swipe">
<button class="jk-btn jk-btn-pass" wire:click="rewind" title="Urungkan aksi terakhir">↩</button>
<button class="jk-btn jk-btn-pass" wire:click="pass" title="Lewati (geser kiri / panah kiri)">✕</button>
<button class="jk-btn jk-btn-super" wire:click="superlike" title="Super Like">✦</button>
<button class="jk-btn jk-btn-like" wire:click="like" title="Suka (geser kanan / panah kanan)">❤️</button>
</div>
<p class="jk-muted" style="text-align:center;font-size:11px;margin-top:8px">Geser kanan = Like · kiri = Lewati · atau pakai tombol &amp; panah keyboard</p>
@if($status)
<div class="jk-muted" style="text-align:center;font-size:12px" role="status">{{ $status }}</div>
@endif
@if($matchedUserId)
<div class="jk-modal-bg jk-match-bg" style="display:flex" role="dialog" aria-modal="true" aria-label="Match baru!">
<div class="jk-modal jk-match-pop" style="text-align:center" x-data x-init="$nextTick(() => $el.querySelector('a, button')?.focus())">
<div class="jk-confetti" aria-hidden="true"><span>🎉</span><span>💘</span><span>✨</span><span>🎊</span><span>💖</span><span>⭐</span></div>
<div style="font-size:44px" aria-hidden="true">💘</div>
<div class="jk-h2">It's a Match!</div>
<p class="jk-muted">Kamu dan <strong>{{ $matchedName }}</strong> saling suka.</p>
<div style="display:flex;gap:8px;margin-top:14px">
<a class="jk-btn jk-btn-like" style="flex:1;text-decoration:none;text-align:center" href="/matches">Lihat Matches</a>
<a class="jk-btn jk-btn-pass" style="flex:1;text-decoration:none;text-align:center" href="/profile/{{ $matchedUserId }}">Profil dia</a>
</div>
<button class="jk-pill" style="margin-top:10px" wire:click="$set('matchedUserId', null)">Lanjut swipe</button>
</div>
</div>
@endif
</div>
</div>
