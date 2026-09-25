@extends('layouts.member')
@section('title', 'Story — Jodohku')
@section('content')
<a href="/stories" class="jk-muted">← Semua story</a>
<div class="jk-card" style="margin-top:8px;max-width:520px">
@if($story->media_path)
<img src="{{ asset('storage/'.$story->media_path) }}" alt="Media story" style="width:100%;border-radius:12px 12px 0 0;display:block" loading="lazy" onerror="this.remove()">
@endif
<div class="jk-card-body">
<div style="display:flex;gap:8px;align-items:center">
<div class="jk-avatar sm">@if($story->user?->avatarUrl())<img src="{{ $story->user->avatarUrl() }}" alt="">@else{{ strtoupper(substr((string)($story->user?->displayName() ?? '?'),0,1)) }}@endif</div>
<div><strong>{{ $story->user?->displayName() ?? 'Member' }}</strong><div class="jk-muted" style="font-size:11px">{{ $story->created_at?->diffForHumans() }} · kedaluwarsa {{ $story->expires_at?->diffForHumans() }}</div></div>
</div>
@if($story->body)<p style="margin:10px 0 0">{{ $story->body }}</p>@endif
<div style="display:flex;gap:8px;margin-top:12px;align-items:center;flex-wrap:wrap">
<form method="POST" action="/stories/{{ $story->id }}/reaksi" style="display:inline">@csrf<button class="jk-pill" type="submit">♡ {{ $story->reactions_count }}</button></form>
<span class="jk-muted" style="font-size:12px">👁 {{ $story->views_count }} dilihat</span>
@if((int) auth()->id() !== (int) $story->user_id)
<button class="jk-pill" type="button" onclick="fetch('/chat/create', { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json' }, body: JSON.stringify({ user_id: {{ (int) $story->user_id }} }) }).then(r => r.json()).then(j => { if (j.conversation_id) window.location.href = '/chat/' + j.conversation_id; else window.jkToast('Gagal membuka chat', false); }).catch(() => window.jkToast('Gagal membuka chat', false))">💬 Balas via chat</button>
@endif
@if((int) auth()->id() === (int) $story->user_id || auth()->user()?->isStaff())
<form method="POST" action="/stories/{{ $story->id }}" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Hapus</button></form>
@endif
</div>
@if($viewers->isNotEmpty())
<div class="jk-muted" style="font-size:12px;margin-top:10px">Dilihat: {{ $viewers->map(fn ($v) => $v->user?->displayName())->filter()->take(10)->implode(', ') }}</div>
@endif
</div>
</div>
@endsection
