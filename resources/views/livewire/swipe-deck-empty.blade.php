<div>
<div wire:loading aria-live="polite" aria-label="Memuat kandidat">
<div class="jk-skeleton" style="height:420px"></div>
</div>
<div wire:loading.remove>
@include('components.empty', ['icon' => 'hati', 'title' => 'Kartu habis', 'hint' => 'Longgarkan filter atau kembali lagi nanti.'])
<div style="text-align:center;margin-top:10px"><a class="jk-btn jk-btn-pass" style="text-decoration:none;display:inline-block;max-width:280px" href="/discover">Mode grid</a></div>
</div>
</div>
