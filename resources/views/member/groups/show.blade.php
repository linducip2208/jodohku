@extends('layouts.member')
@section('title', ($group->name ?? 'Komunitas').' — Jodohku')
@section('content')
@php $me = auth()->user(); $isMember = $group->hasMember((int) $me->id); $isManager = $group->isManager((int) $me->id); @endphp
<div class="jk-card"><div class="jk-card-body">
<div class="jk-name" style="font-size:22px">{{ $group->name }}</div>
<div class="jk-muted">{{ $group->category ?? 'Umum' }} · {{ $group->members_count }} anggota · {{ $group->visibility?->value ?? $group->visibility }}</div>
@if($group->description)<p style="margin:8px 0 0">{{ $group->description }}</p>@endif
@if($group->rules)<details style="margin-top:8px"><summary class="jk-pill" style="cursor:pointer;display:inline-block">Aturan grup</summary><p class="jk-muted">{{ $group->rules }}</p></details>@endif
<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
@if($isMember)
<form method="POST" action="/groups/{{ $group->id }}/leave" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Keluar</button></form>
@else
<form method="POST" action="/groups/{{ $group->id }}/join" style="display:inline">@csrf<button class="jk-btn jk-btn-like" style="flex:none;padding:8px 16px" type="submit">Gabung</button></form>
@endif
</div>
</div></div>
@if($isMember)
<div class="jk-section jk-form" style="padding:12px 14px;margin-top:12px">
<form method="POST" action="/komunitas">@csrf<input type="hidden" name="group_id" value="{{ $group->id }}">
<div style="display:flex;gap:8px"><input class="jk-input" style="flex:1" name="body" maxlength="1000" required placeholder="Posting ke {{ $group->name }}…"><button class="jk-btn jk-btn-like" style="flex:none;padding:10px 16px" type="submit">Kirim</button></div>
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
