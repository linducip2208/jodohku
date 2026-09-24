@extends('layouts.member')
@section('title', 'Stories — Jodohku')
@section('content')
<h1 class="jk-h1">Stories</h1>
<p class="jk-muted">Cerita 24 jam dari member yang kamu ikuti.</p>
<div class="jk-section jk-form" style="padding:12px 14px;margin-bottom:12px">
<form method="POST" action="/stories" enctype="multipart/form-data">@csrf
<label class="jk-muted" style="font-size:12.5px" for="story-body">Bagikan story…</label>
<div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap">
<input id="story-body" class="jk-input" style="flex:1;min-width:160px" name="body" maxlength="500" placeholder="Tulis sesuatu (atau pilih foto)…">
<input type="file" name="media" accept="image/*,video/*" aria-label="Media story">
<input type="hidden" name="type" value="text">
<button class="jk-btn jk-btn-like" style="flex:none;padding:10px 16px" type="submit">Kirim</button>
</div>
</form>
</div>
<div class="jk-row-scroll" role="list">
@forelse($tray as $st)
<a role="listitem" href="/stories/{{ $st->id }}" style="text-decoration:none;text-align:center">
<div class="jk-avatar" style="margin:0 auto;border:2px solid #f43f5e">@if($st->user?->avatarUrl())<img src="{{ $st->user->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else{{ strtoupper(substr((string)($st->user?->displayName() ?? '?'),0,1)) }}@endif</div>
<div class="jk-muted" style="font-size:10px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ \Illuminate\Support\Str::limit($st->user?->displayName() ?? '?', 10) }}</div>
</a>
@empty @include('components.empty', ['icon' => 'cerah', 'title' => 'Belum ada story', 'hint' => 'Ikuti member atau buat story pertamamu.']) @endforelse
</div>
@endsection
