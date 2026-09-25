<div>
<div class="jk-tabs" role="tablist" aria-label="Kategori discovery">
@foreach(['recommended' => 'Untuk Anda', 'new' => 'Terbaru', 'active' => 'Online', 'nearby' => 'Terdekat', 'serious' => 'Serius'] as $key => $label)
<button class="jk-tab {{ $tab === $key ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')">{{ $label }}</button>
@endforeach
</div>
<details class="jk-section" style="padding:10px 14px">
<summary class="jk-muted" style="cursor:pointer;font-size:13px;font-weight:700" aria-label="Filter pencarian">Filter</summary>
<div class="jk-filterbar" style="margin-top:10px;margin-bottom:0" wire:ignore.self>
<input class="jk-input" style="min-width:140px;flex:1" type="search" wire:model.live="keyword" placeholder="Cari nama, kota, minat…" aria-label="Kata kunci">
<input class="jk-input" style="width:70px" type="number" wire:model.live="minAge" placeholder="Min" aria-label="Umur minimal"><span class="jk-muted">–</span>
<input class="jk-input" style="width:70px" type="number" wire:model.live="maxAge" placeholder="Maks" aria-label="Umur maksimal">
<select class="jk-select" wire:model.live="gender" aria-label="Gender"><option value="">Semua gender</option><option value="female">Perempuan</option><option value="male">Laki-laki</option></select>
<input class="jk-input" wire:model.live="city" placeholder="Kota" aria-label="Kota">
<input class="jk-input" wire:model.live="education" placeholder="Pendidikan" aria-label="Pendidikan">
<input class="jk-input" wire:model.live="occupation" placeholder="Pekerjaan" aria-label="Pekerjaan">
<input class="jk-input" wire:model.live="religion" placeholder="Agama" aria-label="Agama">
<select class="jk-select" wire:model.live="relationshipGoal" aria-label="Tujuan hubungan"><option value="">Semua tujuan</option><option value="marriage">Menikah</option><option value="serious_relationship">Serius</option><option value="dating">Pacaran</option><option value="friendship">Pertemanan</option></select>
<select class="jk-select" wire:model.live="sort" aria-label="Urutan"><option value="compatibility">Skor tertinggi</option><option value="newest">Terbaru</option><option value="active">Terakhir aktif</option><option value="distance">Terdekat</option><option value="popularity">Populer</option></select>
<label class="jk-pill"><input type="checkbox" wire:model.live="verified"> Verified</label>
<label class="jk-pill"><input type="checkbox" wire:model.live="online"> Online</label>
<label class="jk-pill"><input type="checkbox" wire:model.live="premium"> Premium</label>
<button class="jk-btn jk-btn-pass" wire:click="resetFilters" style="flex:none">Reset</button>
</div>
@if($saved->isNotEmpty())
<div style="margin-top:10px" aria-label="Filter tersimpan">
<div class="jk-muted" style="font-size:12px;font-weight:700;margin-bottom:6px">Filter tersimpan</div>
<div style="display:flex;gap:6px;flex-wrap:wrap">
@foreach($saved as $sf)
<a class="jk-pill" style="text-decoration:none" href="/discover?{{ http_build_query(array_merge(['tab' => 'orang', 'mode' => request('mode', 'grid')], $sf->filters)) }}">{{ $sf->name }}</a>
@endforeach
</div>
</div>
@endif
<div style="display:flex;gap:6px;margin-top:10px">
<input class="jk-input" style="flex:1" wire:model="filterName" maxlength="60" placeholder="Nama filter ini…" aria-label="Nama filter tersimpan">
<button class="jk-btn jk-btn-pass" wire:click="saveCurrentFilter" style="flex:none" title="Simpan filter aktif">Simpan</button>
</div>
</details>
<div wire:loading aria-live="polite" aria-label="Memuat kandidat">
<div class="jk-grid">@for($i = 0; $i < 6; $i++)<div><div class="jk-skeleton" style="height:220px;margin-bottom:8px"></div><div class="jk-skeleton" style="height:14px;width:70%;margin-bottom:6px"></div><div class="jk-skeleton" style="height:12px;width:45%"></div></div>@endfor</div>
<p class="jk-muted" style="margin-top:8px"><span class="jk-spinner"></span> Memuat kandidat…</p>
</div>
<div wire:loading.remove>
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
</div>
