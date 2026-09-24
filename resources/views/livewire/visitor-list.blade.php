<div>
<div style="display:flex;gap:8px;margin-bottom:12px">
<button class="jk-pill" wire:click="setTab('visitors')" style="{{ $tab === 'visitors' ? 'background:#f43f5e;color:#fff' : '' }}">Pengunjung</button>
<button class="jk-pill" wire:click="setTab('likers')" style="{{ $tab === 'likers' ? 'background:#f43f5e;color:#fff' : '' }}">Yang menyukaimu</button>
</div>
<div wire:loading aria-live="polite" aria-label="Memuat daftar">
<div class="jk-chat-list">@for($i = 0; $i < 4; $i++)<div class="jk-chat-item"><div class="jk-skeleton" style="width:44px;height:44px;border-radius:50%;flex:none"></div><div style="flex:1"><div class="jk-skeleton" style="height:14px;width:45%;margin-bottom:6px"></div><div class="jk-skeleton" style="height:12px;width:65%"></div></div></div>@endfor</div>
</div>
<div wire:loading.remove>
@if($tab === 'visitors')
<div class="jk-chat-list">@forelse($visitors as $v)<div class="jk-chat-item"><div class="jk-avatar">{{ strtoupper(substr((string)($v->viewer?->displayName() ?? '?'),0,1)) }}</div><div><strong>{{ $v->viewer?->displayName() ?? 'Member' }}</strong><div class="jk-muted">{{ $v->created_at?->diffForHumans() }}</div></div></div>@empty @include('components.empty', ['icon' => 'baru', 'title' => 'Belum ada pengunjung', 'hint' => 'Lengkapi profil + boost agar dilihat.']) @endforelse</div>
@else
<div class="jk-chat-list">@forelse($likers as $l)<div class="jk-chat-item"><div class="jk-avatar">{{ strtoupper(substr((string)($l->liker?->displayName() ?? '?'),0,1)) }}</div><div><strong>{{ $l->liker?->displayName() ?? 'Member' }}</strong><div class="jk-muted">{{ $l->created_at?->diffForHumans() }} · {{ auth()->user()?->isPremium() ? 'terlihat penuh (Premium)' : 'upgrade Premium untuk chat langsung' }}</div></div></div>@empty @include('components.empty', ['icon' => 'hati', 'title' => 'Belum ada yang like', 'hint' => 'Tambah foto & bio menarik.']) @endforelse</div>
@endif
</div>
</div>
