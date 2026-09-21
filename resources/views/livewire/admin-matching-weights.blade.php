<div class="card"><div class="card-header"><h3 class="card-title">Bobot Matching (total dinormalisasi 100)</h3></div>
<div class="card-body">
@foreach($weights as $k => $v)
<div class="mb-2"><label class="form-label">{{ $k }}: {{ $v }}</label><input type="range" class="form-range" min="0" max="40" step="1" wire:model.live="weights.{{ $k }}"></div>
@endforeach
<button class="btn btn-primary" wire:click="save">Simpan Bobot</button>
@if($saved)<div class="alert alert-success mt-2">{{ $saved }}</div>@endif
<div class="text-secondary mt-2">Sumber: MatchingEngine::weights() + config/matchmaking.php. Boost tidak mengubah skor — hanya urutan tampil (DiscoveryService).</div>
</div></div>
