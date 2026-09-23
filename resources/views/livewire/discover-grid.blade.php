<div>
<div class="jk-tabs" role="tablist" aria-label="Kategori discovery">
@foreach(['recommended' => 'Rekomendasi', 'new' => 'Anggota baru', 'active' => 'Aktif', 'nearby' => 'Dekat', 'serious' => 'Serius menikah'] as $key => $label)
<button class="jk-tab {{ $tab === $key ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')">{{ $label }}</button>
@endforeach
</div>
<div class="jk-filterbar" wire:ignore.self>
<input class="jk-input" style="min-width:140px;flex:1" type="search" wire:model.live="keyword" placeholder="Cari nama, kota, minat…" aria-label="Kata kunci">
<input class="jk-input" style="width:70px" type="number" wire:model.live="minAge" placeholder="Min" aria-label="Umur minimal"><span class="jk-muted">–</span>
<input class="jk-input" style="width:70px" type="number" wire:model.live="maxAge" placeholder="Maks" aria-label="Umur maksimal">
<select class="jk-select" wire:model.live="gender" aria-label="Gender"><option value="">Semua gender</option><option value="female">Perempuan</option><option value="male">Laki-laki</option></select>
<input class="jk-input" wire:model.live="city" placeholder="Kota" aria-label="Kota">
<input class="jk-input" wire:model.live="education" placeholder="Pendidikan" aria-label="Pendidikan">
<select class="jk-select" wire:model.live="sort" aria-label="Urutan"><option value="compatibility">Skor tertinggi</option><option value="newest">Terbaru</option><option value="active">Terakhir aktif</option><option value="distance">Terdekat</option><option value="popularity">Populer</option></select>
<label class="jk-pill"><input type="checkbox" wire:model.live="verified"> Verified</label>
<label class="jk-pill"><input type="checkbox" wire:model.live="online"> Online</label>
<label class="jk-pill"><input type="checkbox" wire:model.live="premium"> Premium</label>
<button class="jk-btn jk-btn-pass" wire:click="resetFilters" style="flex:none">Reset</button>
</div>
@if($candidates->isEmpty())
@include('components.empty', ['icon' => 'cari', 'title' => auth()->check() ? 'Belum ada kandidat — longgarkan filter' : 'Masuk untuk melihat kandidat', 'hint' => auth()->check() ? 'Coba naikkan jarak / rentang usia.' : 'Daftar gratis, 2 menit.'])
@else
<div class="jk-grid">
@foreach($candidates as $c)
@include('components.profile-card', ['user' => $c, 'score' => $c->compatibility_score ?? null])
@endforeach
</div>
@if(!empty($hasMore))
<div style="text-align:center;margin-top:14px"><button class="jk-btn jk-btn-pass" style="max-width:280px" wire:click="loadMore">Muat lebih banyak</button></div>
@endif
@endif
</div>
