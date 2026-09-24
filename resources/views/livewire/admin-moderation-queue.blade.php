<div class="card"><div class="card-header"><h3 class="card-title">Moderation Queue</h3></div>
<div class="card-body">
@if($status)<div class="alert alert-info">{{ $status }}</div>@endif
<div style="display:flex;gap:8px;margin-bottom:10px">
<button class="btn btn-sm btn-success" wire:click="bulkApprove" @disabled(empty($selected))>Approve terpilih ({{ count($selected) }})</button>
<button class="btn btn-sm btn-danger" wire:click="bulkReject" @disabled(empty($selected))>Reject terpilih ({{ count($selected) }})</button>
</div>
<table class="table table-vcenter"><thead><tr><th><span class="visually-hidden">Pilih</span></th><th>ID</th><th>Type</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $it)<tr><td><input type="checkbox" wire:model.live="selected" value="{{ $it->id }}" aria-label="Pilih item {{ $it->id }}"></td><td>{{ $it->id }}</td><td>{{ $it->type ?? $it->target_type ?? '—' }}</td><td>{{ $it->status }}</td>
<td><button class="btn btn-sm btn-success" wire:click="approve({{ $it->id }})">Approve</button>
<button class="btn btn-sm btn-danger" wire:click="reject({{ $it->id }})">Reject</button></td></tr>
@empty<tr><td colspan="5" class="text-secondary">Antrean kosong. Semua konten aman. ✅</td></tr>@endforelse
</tbody></table>
</div></div>
