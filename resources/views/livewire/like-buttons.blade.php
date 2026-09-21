<div>
<div class="jk-actions" role="group" aria-label="Aksi profil">
<button class="jk-btn jk-btn-pass" wire:click="pass" title="Lewati profil ini">Lewati</button>
<button class="jk-btn jk-btn-fav" wire:click="favorite" title="Simpan ke favorit">Favorit</button>
<button class="jk-btn jk-btn-super" wire:click="superlike" title="Super Like (kuota/kredit)">Super</button>
<button class="jk-btn jk-btn-like" wire:click="like" title="Sukai profil ini">Like</button>
</div>
@if($status)<div class="jk-muted" style="margin-top:6px;font-size:12px" role="status">{{ $status }}</div>@endif
</div>
