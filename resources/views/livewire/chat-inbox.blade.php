<div>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
@foreach(['all' => 'Semua', 'unread' => 'Belum dibaca', 'online' => 'Online', 'verified' => 'Verified', 'premium' => 'Premium', 'archived' => 'Arsip'] as $k => $label)
<button wire:click="setFilter('{{ $k }}')" class="jk-pill" style="{{ $filter === $k ? 'background:#f43f5e;color:#fff' : '' }}">{{ $label }}</button>
@endforeach
<input class="jk-input" style="margin-left:auto" wire:model.live="search" placeholder="Cari nama...">
</div>
<div class="jk-chat-list" wire:poll.15s>
@forelse($items as $conv)
@php $me = auth()->user(); $other = $me ? $conv->otherUser($me->id) : null; $last = $conv->latestMessages->first() ?? $conv->messages->first(); @endphp
<a class="jk-chat-item" href="/chat/{{ $conv->id }}">
<div class="jk-avatar">@if($other?->avatar_path)<img src="{{ $other->avatar_path }}" alt="">@else{{ strtoupper(substr((string)($other?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1;min-width:0">
<div style="display:flex;gap:6px;align-items:center"><strong>{{ $other?->displayName() ?? 'Member' }}</strong>
@if($other?->is_verified)<span style="color:#0ea5e9">✔</span>@endif
@if($other?->is_online)<span style="color:#22c55e;font-size:11px">● online</span>@endif
</div>
<div class="jk-muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $last?->body ?? 'Mulai percakapan 👋' }}</div>
</div>
</a>
@empty
@include('components.empty', ['icon' => '💬', 'title' => 'Belum ada percakapan', 'hint' => 'Like profil di Discover untuk mulai chat.'])
@endforelse
</div>
</div>
