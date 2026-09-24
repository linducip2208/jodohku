@props(['post', 'likedIds' => [], 'compact' => false])
@php
$author = $post->user;
$name = $author?->displayName() ?? 'Member';
$initial = strtoupper(substr((string) $name, 0, 1));
$liked = in_array($post->id, (array) $likedIds, true);
$mine = auth()->id() && $author && (int) $author->id === (int) auth()->id();
@endphp
<article class="jk-section jk-post" aria-label="Postingan {{ $name }}">
<div style="display:flex;gap:10px;align-items:center">
<a href="{{ $author ? '/profile/'.$author->id : '#' }}" class="jk-avatar sm" aria-label="Profil {{ $name }}">@if($author?->avatarUrl())
<img src="{{ $author->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">
@else
{{ $initial }}
@endif</a>
<div style="flex:1;min-width:0">
<div><a href="{{ $author ? '/profile/'.$author->id : '#' }}" style="color:inherit;text-decoration:none"><strong>{{ $name }}</strong></a>
@if($author?->is_verified)<span class="jk-badge-verified" title="Terverifikasi" aria-label="Terverifikasi"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8 12.5l2.7 2.7L16.5 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>@endif
@if($author?->is_online)<span style="color:#22c55e;font-size:11px" title="Online">●</span>@endif</div>
<div class="jk-muted" style="font-size:11px">{{ $post->created_at?->diffForHumans() }}{{ $post->group_id ? ' · grup' : '' }}</div>
</div>
@if(! $mine)
<details style="position:relative">
<summary class="jk-pill" style="cursor:pointer;list-style:none" aria-label="Opsi postingan">···</summary>
<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
<form method="POST" action="/komunitas/postingan/{{ $post->id }}/laporkan">@csrf<button class="jk-pill" type="submit">Laporkan</button></form>
@if($author)<form method="POST" action="/safety/block" onsubmit="return confirm('Blokir {{ $name }}?')">@csrf<input type="hidden" name="user_id" value="{{ $author->id }}"><button class="jk-pill" type="submit">Blokir penulis</button></form>@endif
</div>
</details>
@endif
</div>
<p style="margin:10px 0 0">{{ $post->body }}</p>
@php $media = collect((array) ($post->media_paths ?? []))->filter(fn ($m) => is_string($m) && $m !== '')->take(2)->values(); @endphp
@if($media->isNotEmpty())
<div style="display:grid;gap:6px;margin-top:10px;grid-template-columns:repeat({{ $media->count() > 1 ? 2 : 1 }},minmax(0,1fr))">
@foreach($media as $m)
@php $src = \Illuminate\Support\Str::startsWith($m, ['http://', 'https://']) ? $m : asset('storage/'.ltrim($m, '/')); @endphp
<img src="{{ $src }}" alt="Foto postingan {{ $name }}" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:12px;display:block" onerror="this.remove()">
@endforeach
</div>
@endif
<div style="display:flex;gap:4px;align-items:center;margin-top:10px">
<form method="POST" action="/komunitas/{{ $post->id }}/like" style="display:inline">@csrf<button class="jk-pill" type="submit" aria-label="Suka">{{ $liked ? '♥' : '♡' }} {{ $post->likes_count }}</button></form>
<span class="jk-muted" style="font-size:12px">{{ $post->comments_count }} komentar</span>
</div>
@if(! $compact)
@foreach($post->comments ?? [] as $c)
<div style="margin-top:8px;padding:8px 10px;background:#fafafb;border-radius:10px;font-size:13px"><strong>{{ $c->user?->displayName() ?? 'Member' }}</strong> {{ $c->body }}</div>
@endforeach
<form method="POST" action="/komunitas/{{ $post->id }}/komentar" style="display:flex;gap:6px;margin-top:8px">@csrf<input class="jk-input" style="flex:1" name="body" maxlength="500" required placeholder="Tulis komentar…" aria-label="Komentar"><button class="jk-pill" type="submit">Kirim</button></form>
@else
<a class="jk-pill" style="margin-top:8px;display:inline-block" href="/komunitas">Lihat komentar</a>
@endif
</article>
