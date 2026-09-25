<div>
<div class="jk-actions" role="group" aria-label="Aksi profil">
<button class="jk-btn jk-btn-pass" wire:click="pass" title="Lewati profil ini">Lewati</button>
<button class="jk-btn jk-btn-fav" wire:click="favorite" title="Simpan ke favorit">Favorit</button>
<button class="jk-btn jk-btn-super" wire:click="superlike" title="Super Like (kuota/kredit)">Super</button>
<button class="jk-btn jk-btn-like" wire:click="like" title="Sukai profil ini">Like</button>
</div>
@if($status)<div class="jk-muted" style="margin-top:6px;font-size:12px" role="status">{{ $status }}</div>@endif
@if($matchedUserId)
<div class="jk-modal-bg" style="display:flex" role="dialog" aria-modal="true" aria-label="Match baru!">
<div class="jk-modal" style="text-align:center" x-data x-init="$nextTick(() => $el.querySelector('a, button')?.focus())">
<div style="font-size:44px" aria-hidden="true">💘</div>
<div class="jk-h2">It's a Match!</div>
<p class="jk-muted">Kamu dan <strong>{{ $matchedName }}</strong> saling suka. Sapa duluan — kesopanan membuka jalan taaruf.</p>
<div style="display:flex;gap:8px;margin-top:14px">
<a class="jk-btn jk-btn-like" style="flex:1;text-decoration:none;text-align:center" href="/matches">Lihat Matches</a>
<a class="jk-btn jk-btn-pass" style="flex:1;text-decoration:none;text-align:center" href="/profile/{{ $matchedUserId }}">Profil dia</a>
</div>
<button class="jk-pill" style="margin-top:10px" wire:click="$set('matchedUserId', null)">Nanti saja</button>
</div>
</div>
@endif
</div>
