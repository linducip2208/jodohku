<div>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
@foreach(['all' => 'Semua', 'unread' => 'Belum dibaca', 'online' => 'Online', 'verified' => 'Verified', 'premium' => 'Premium', 'archived' => 'Arsip'] as $k => $label)
<button wire:click="setFilter('{{ $k }}')" class="jk-pill" style="{{ $filter === $k ? 'background:#f43f5e;color:#fff' : '' }}">{{ $label }}</button>
@endforeach
<input class="jk-input" style="margin-left:auto" wire:model.live="search" placeholder="Cari nama...">
</div>
<div wire:loading aria-live="polite" aria-label="Memuat percakapan">
<div class="jk-chat-list">@for($i = 0; $i < 5; $i++)<div class="jk-chat-item"><div class="jk-skeleton" style="width:44px;height:44px;border-radius:50%;flex:none"></div><div style="flex:1"><div class="jk-skeleton" style="height:14px;width:40%;margin-bottom:6px"></div><div class="jk-skeleton" style="height:12px;width:80%"></div></div></div>@endfor</div>
</div>
<div wire:loading.remove>
<div class="jk-chat-list" wire:poll.15s>
@forelse($items as $conv)
@php $me = auth()->user(); $other = $me ? $conv->otherUser($me->id) : null; $last = $conv->relationLoaded('latestMessages') ? $conv->latestMessages->first() : null; @endphp
<a class="jk-chat-item" href="/chat/{{ $conv->id }}">
<div class="jk-avatar">@if($other?->avatarUrl())<img src="{{ $other->avatarUrl() }}" alt="Foto {{ $other->displayName() }}">@else{{ strtoupper(substr((string)($other?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1;min-width:0">
<div style="display:flex;gap:6px;align-items:center"><strong>{{ $other?->displayName() ?? 'Member' }}</strong>
@if($other?->is_verified)<span style="color:#0ea5e9" title="Terverifikasi" aria-label="Terverifikasi">✓</span>@endif
@if($other?->is_online)<span style="color:#22c55e;font-size:11px">● online</span>@endif
</div>
<div class="jk-muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $last?->body ?? 'Mulai percakapan 👋' }}</div>
</div>
</a>
@empty
@include('components.empty', ['icon' => 'chat', 'title' => 'Belum ada percakapan', 'hint' => 'Like profil di Discover untuk mulai chat.'])
@endforelse
</div>
</div>
</div>
