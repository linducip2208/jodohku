<div class="card"><div class="card-header"><h3 class="card-title">Operator Panel — Virtual Conversations</h3></div>
<div class="card-body">
@if($status)<div class="alert alert-info">{{ $status }}</div>@endif
<table class="table table-vcenter"><thead><tr><th>ID</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($items as $vc)<tr><td>{{ $vc->id }}</td><td><span class="badge bg-blue-lt">{{ $vc->status }}</span></td>
<td><button class="btn btn-sm btn-primary" wire:click="takeover({{ $vc->id }})">Takeover</button>
<button class="btn btn-sm btn-warning" wire:click="pause({{ $vc->id }})">Pause</button>
<button class="btn btn-sm btn-success" wire:click="resume({{ $vc->id }})">Resume</button></td></tr>
@empty<tr><td colspan="3" class="text-secondary">Tidak ada virtual conversation aktif.</td></tr>@endforelse
</tbody></table>
<div class="text-secondary">Via OperatorService::takeover/pause/resume + AuditService.</div>
</div></div>
