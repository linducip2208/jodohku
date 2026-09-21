@extends('layouts.admin')
@section('title', 'Photo Moderation')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Photo Moderation Queue</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Preview</th><th>Dimensi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@php $photos = $photos ?? \App\Models\ProfilePhoto::pendingReview()->with('user')->take(25)->get(); @endphp
@forelse($photos as $p)
<tr>
<td>{{ $p->id }}</td>
<td>{{ $p->user?->display_name ?? $p->user_id }}</td>
<td>@if($p->thumbnail_path)<img src="{{ asset('storage/'.$p->thumbnail_path) }}" width="64" height="64" style="object-fit:cover;border-radius:8px"> @else — @endif</td>
<td>{{ $p->width }}×{{ $p->height }}</td>
<td><span class="badge bg-{{ $p->status === 'approved' ? 'green' : ($p->status === 'rejected' ? 'red' : 'yellow') }}">{{ $p->status }}</span>@if($p->is_private) <i class="ti ti-lock text-secondary" title="Private"></i>@endif</td>
<td>
<form method="POST" action="{{ route('admin.photos.moderate', $p->id) }}" style="display:inline">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-success">Approve</button></form>
<form method="POST" action="{{ route('admin.photos.moderate', $p->id) }}" style="display:inline">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-danger">Reject</button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="text-secondary">Antrian kosong. Semua foto sudah direview.</td></tr>
@endforelse
</tbody></table></div>
@if(method_exists($photos, 'links'))<div class="card-footer">{{ $photos->links() }}</div>@endif
</div>
@endsection
