@extends('layouts.member')
@section('title', ($group->name ?? 'Komunitas').' — Jodohku')
@section('content')
@php $meId = (int) (auth()->id() ?? 0); $isMember = $meId > 0 && $group->hasMember($meId); $isManager = $meId > 0 && $group->isManager($meId); $isPrivate = (($group->visibility?->value ?? (string) $group->visibility) === 'private'); @endphp
@if($group->cover_path)
<div class="jk-card" style="overflow:hidden;padding:0;margin-bottom:12px"><img src="{{ asset('storage/'.$group->cover_path) }}" alt="Cover {{ $group->name }}" loading="lazy" style="width:100%;aspect-ratio:3/1;object-fit:cover;display:block" onerror="this.remove()"></div>
@endif
<div class="jk-card"><div class="jk-card-body">
<div class="jk-name" style="font-size:22px">{{ $group->name }}</div>
<div class="jk-muted">{{ $group->category ?? 'Umum' }} · {{ $group->members_count }} anggota · {{ $group->visibility?->value ?? $group->visibility }}</div>
@if($group->description)<p style="margin:8px 0 0">{{ $group->description }}</p>@endif
@if($group->rules)<details style="margin-top:8px"><summary class="jk-pill" style="cursor:pointer;display:inline-block">Aturan grup</summary><p class="jk-muted">{{ $group->rules }}</p></details>@endif
<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
@if($isMember)
<form method="POST" action="/groups/{{ $group->id }}/leave" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Keluar</button></form>
@elseif($isPrivate && $meId > 0)
<form method="POST" action="/groups/{{ $group->id }}/request" style="display:inline">@csrf<button class="jk-btn jk-btn-like" style="flex:none;padding:8px 16px" type="submit">Minta bergabung</button></form>
@elseif($meId > 0)
<form method="POST" action="/groups/{{ $group->id }}/join" style="display:inline">@csrf<button class="jk-btn jk-btn-like" style="flex:none;padding:8px 16px" type="submit">Gabung</button></form>
@endif
</div>
@if($isManager)
<details style="margin-top:10px"><summary class="jk-pill" style="cursor:pointer;display:inline-block">Kelola grup</summary>
<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
<form method="POST" action="/groups/{{ $group->id }}/cover" enctype="multipart/form-data" style="display:flex;gap:6px">@csrf<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required aria-label="Cover grup"><button class="jk-pill" type="submit">Upload cover</button></form>
<form method="POST" action="/groups/{{ $group->id }}/invite" style="display:flex;gap:6px">@csrf<input class="jk-input" name="username" required maxlength="190" placeholder="username…" aria-label="Username diundang"><button class="jk-pill" type="submit">Undang</button></form>
</div>
@php $pending = \App\Models\GroupJoinRequest::with('user:id,display_name,name')->where('group_id', $group->id)->where('status', 'pending')->latest('id')->limit(20)->get(); @endphp
@if($pending->isNotEmpty())
<div class="jk-muted" style="font-size:12px;margin:8px 0 4px">Permintaan bergabung ({{ $pending->count() }})</div>
@foreach($pending as $pr)
<div style="display:flex;gap:6px;align-items:center;margin-bottom:6px"><span style="flex:1">{{ $pr->user?->displayName() ?? 'Member' }}</span>
<form method="POST" action="/groups/{{ $group->id }}/requests/{{ $pr->id }}" style="display:inline">@csrf<input type="hidden" name="action" value="approve"><button class="jk-pill" type="submit">Terima</button></form>
<form method="POST" action="/groups/{{ $group->id }}/requests/{{ $pr->id }}" style="display:inline">@csrf<input type="hidden" name="action" value="reject"><button class="jk-pill" type="submit">Tolak</button></form>
</div>
@endforeach
@endif
</details>
@endif
</div></div>
@if($isMember)
<div class="jk-section jk-form" style="padding:12px 14px;margin-top:12px">
<form method="POST" action="/komunitas" enctype="multipart/form-data">@csrf<input type="hidden" name="group_id" value="{{ $group->id }}">
<div style="display:flex;gap:8px;flex-wrap:wrap"><input class="jk-input" style="flex:1;min-width:160px" name="body" maxlength="1000" required placeholder="Posting ke {{ $group->name }}…"><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="Foto postingan" style="max-width:220px"><button class="jk-btn jk-btn-like" style="flex:none;padding:10px 16px" type="submit">Kirim</button></div>
</form>
</div>
@endif
<div class="jk-feed" style="margin-top:12px">
@forelse($posts as $p) @include('components.social-post', ['post' => $p]) @empty @include('components.empty', ['icon' => 'chat', 'title' => 'Belum ada postingan', 'hint' => 'Jadilah yang pertama berbagi.']) @endforelse
</div>
<div style="margin-top:12px">{{ $posts->links() }}</div>
<section class="jk-section" aria-label="Anggota" style="margin-top:12px"><h2 class="jk-h2">Anggota</h2>
<div class="jk-chat-list">
@foreach($members as $m)<div class="jk-chat-item"><div class="jk-avatar sm">{{ strtoupper(substr((string)($m->user?->displayName() ?? '?'),0,1)) }}</div><div style="flex:1"><strong>{{ $m->user?->displayName() ?? 'Member' }}</strong><div class="jk-muted">{{ $m->role }}</div></div></div>@endforeach
</div></section>
@endsection
