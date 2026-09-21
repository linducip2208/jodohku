<div>
<div style="display:flex;gap:8px;margin-bottom:12px">
<button class="jk-pill" wire:click="setTab('visitors')" style="{{ $tab === 'visitors' ? 'background:#f43f5e;color:#fff' : '' }}">👀 Pengunjung</button>
<button class="jk-pill" wire:click="setTab('likers')" style="{{ $tab === 'likers' ? 'background:#f43f5e;color:#fff' : '' }}">❤️ Yang menyukaimu</button>
</div>
@if($tab === 'visitors')
<div class="jk-chat-list">@forelse($visitors as $v)<div class="jk-chat-item"><div class="jk-avatar">{{ strtoupper(substr((string)($v->viewer?->displayName() ?? '?'),0,1)) }}</div><div><strong>{{ $v->viewer?->displayName() ?? 'Member' }}</strong><div class="jk-muted">{{ $v->created_at?->diffForHumans() }}</div></div></div>@empty @include('components.empty', ['icon' => '👀', 'title' => 'Belum ada pengunjung', 'hint' => 'Lengkapi profil + boost agar dilihat.']) @endforelse</div>
@else
<div class="jk-chat-list">@forelse($likers as $l)<div class="jk-chat-item"><div class="jk-avatar">{{ strtoupper(substr((string)($l->liker?->displayName() ?? '?'),0,1)) }}</div><div><strong>{{ $l->liker?->displayName() ?? 'Member' }}</strong><div class="jk-muted">{{ $l->created_at?->diffForHumans() }} · {{ auth()->user()?->isPremium() ? 'terlihat penuh (Premium)' : 'upgrade Premium untuk chat langsung' }}</div></div></div>@empty @include('components.empty', ['icon' => '❤️', 'title' => 'Belum ada yang like', 'hint' => 'Tambah foto & bio menarik.']) @endforelse</div>
@endif
</div>
