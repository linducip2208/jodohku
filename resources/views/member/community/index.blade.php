@extends('layouts.member')
@section('title', 'Komunitas — Jodohku')
@section('content')
<h1 class="jk-h1">Komunitas</h1>
<p class="jk-muted">Cerita dan diskusi member — dimoderasi. <a href="/forums">Forum diskusi</a> · <a href="/events">Events</a></p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section jk-form">
<form method="POST" action="/komunitas">@csrf
<label for="post-body">Apa yang kamu pikirkan?</label>
<textarea id="post-body" name="body" rows="3" maxlength="1000" required placeholder="Bagikan cerita taaruf, tips, atau pertanyaan…"></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Posting</button>
</form>
</div>
<div class="jk-feed">
@forelse($posts as $p)
<article class="jk-section" aria-label="Postingan {{ $p->user?->displayName() }}">
<div style="display:flex;gap:10px;align-items:center">
<div class="jk-avatar sm">@if($p->user?->avatarUrl())<img src="{{ $p->user->avatarUrl() }}" alt="" loading="lazy">@else{{ strtoupper(substr((string)($p->user?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1"><strong>{{ $p->user?->displayName() ?? 'Member' }}</strong> @if($p->user?->is_verified)<span class="jk-badge-verified" title="Terverifikasi">✓</span>@endif<div class="jk-muted" style="font-size:11px">{{ $p->created_at?->diffForHumans() }}</div></div>
@if((int) $p->user_id === (int) auth()->id())<form method="POST" action="/komunitas/{{ $p->id }}" onsubmit="return confirm('Hapus postingan?')">@csrf @method('DELETE')<button class="jk-pill" type="submit">Hapus</button></form>@endif
</div>
<p style="margin:10px 0">{{ $p->body }}</p>
<div style="display:flex;gap:8px;align-items:center">
<form method="POST" action="/komunitas/{{ $p->id }}/like" style="display:inline">@csrf<button class="jk-pill" type="submit">{{ in_array($p->id, $likedIds ?? []) ? '♥' : '♡' }} {{ $p->likes_count }}</button></form>
<span class="jk-muted" style="font-size:12px">{{ $p->comments_count }} komentar</span>
</div>
@foreach($p->comments as $c)
<div style="margin-top:8px;padding:8px 10px;background:#fafafb;border-radius:10px;font-size:13px"><strong>{{ $c->user?->displayName() ?? 'Member' }}</strong> {{ $c->body }}</div>
@endforeach
<form method="POST" action="/komunitas/{{ $p->id }}/komentar" style="display:flex;gap:6px;margin-top:8px">@csrf<input class="jk-input" style="flex:1" name="body" maxlength="500" required placeholder="Tulis komentar…" aria-label="Komentar"><button class="jk-pill" type="submit">Kirim</button></form>
</article>
@empty
@include('components.empty', ['icon' => 'chat', 'title' => 'Belum ada postingan', 'hint' => 'Jadilah yang pertama berbagi cerita.'])
@endforelse
</div>
<div style="margin-top:12px">{{ $posts->links() }}</div>
@endsection
