@extends('layouts.admin')
@section('title', 'Moderasi Video — Jodohku')
@section('content')
<h1>Video menunggu persetujuan ({{ $videos->total() }})</h1>
@if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
<table class="jk-table"><thead><tr><th>ID</th><th>User</th><th>Preview</th><th>Waktu</th><th>Aksi</th></tr></thead><tbody>
@forelse($videos as $v)
<tr>
<td>{{ $v->id }}</td>
<td>{{ $v->user?->displayName() ?? '?' }} (#{{ $v->user_id }})</td>
<td><video src="{{ asset('storage/'.$v->path) }}" style="width:160px" preload="metadata" playsinline controls></video></td>
<td>{{ $v->created_at?->diffForHumans() }}</td>
<td style="white-space:nowrap">
<form method="POST" action="{{ route('admin.moderation.videos.decide', $v->id) }}" style="display:inline">@csrf<input type="hidden" name="action" value="approve"><button type="submit">Setujui</button></form>
<form method="POST" action="{{ route('admin.moderation.videos.decide', $v->id) }}" style="display:inline" onsubmit="return confirm('Tolak & hapus video?')">@csrf<input type="hidden" name="action" value="reject"><button type="submit">Tolak</button></form>
</td>
</tr>
@empty
<tr><td colspan="5">Antrean kosong ✅</td></tr>
@endforelse
</tbody></table>
{{ $videos->links() }}
@endsection
