<div>
<div class="jk-actions">
<button class="jk-btn jk-btn-pass" wire:click="pass" title="Lewati">✕</button>
<button class="jk-btn jk-btn-fav" wire:click="favorite" title="Favorit">⭐</button>
<button class="jk-btn jk-btn-super" wire:click="superlike" title="Superlike">✦</button>
<button class="jk-btn jk-btn-like" wire:click="like" title="Like">❤️</button>
</div>
@if($status)<div class="jk-muted" style="margin-top:6px;font-size:12px">{{ $status }}</div>@endif
</div>
