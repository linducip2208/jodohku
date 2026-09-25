<div>
<div wire:loading aria-live="polite" aria-label="Memuat matches">
<div class="jk-grid">@for($i = 0; $i < 4; $i++)<div><div class="jk-skeleton" style="height:180px;margin-bottom:8px"></div><div class="jk-skeleton" style="height:14px;width:60%"></div></div>@endfor</div>
</div>
<div wire:loading.remove>
@if($matches->isEmpty())
@include('components.empty', ['icon' => 'hati', 'title' => 'Belum ada match', 'hint' => 'Saling like untuk match. Coba Discover!'])
@else
<div class="jk-grid">
@foreach($matches as $mt)
@php $me = auth()->id(); $other = (int) $mt->user_a_id === (int) $me ? $mt->userB : $mt->userA; @endphp
@if($other)
<div>
@include('components.profile-card', ['user' => $other, 'score' => $mt->compatibility_score ?? null, 'compact' => true])
<div x-data="{ openers: [], loading: false }" style="margin-top:6px">
<button class="jk-pill" @click="loading = true; fetch('/ai/icebreakers/{{ $other->id }}', { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(j => { openers = j.icebreakers || []; loading = false; }).catch(() => { loading = false; })" x-show="openers.length === 0 && !loading" aria-label="Saran pembuka untuk {{ $other->displayName() }}">💬 Sapa duluan</button>
<span class="jk-muted" x-show="loading" style="font-size:12px">Menyiapkan saran…</span>
<template x-for="(o, i) in openers" :key="i">
<form method="POST" action="/matches/{{ $other->id }}/icebreaker-send" style="margin-top:6px;display:flex;gap:6px">@csrf<input type="hidden" name="text" :value="o"><button class="jk-pill" type="submit" style="text-align:left" x-text="o.length > 80 ? o.slice(0, 80) + '…' : o"></button></form>
</template>
</div>
<details style="margin-top:6px">
<summary class="jk-pill" style="cursor:pointer;display:inline-block">Catatan privat</summary>
<div style="margin-top:6px;display:flex;gap:6px">
<input class="jk-input" style="flex:1" wire:model="noteBodies.{{ $mt->id }}" placeholder="Catatan hanya untukmu…" maxlength="2000" aria-label="Catatan privat untuk match ini">
<button class="jk-pill" wire:click="saveNote({{ $mt->id }})">Simpan</button>
</div>
</details>
</div>
@endif
@endforeach
</div>
@endif
</div>
</div>
