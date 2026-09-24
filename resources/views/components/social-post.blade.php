@props(['post', 'likedIds' => [], 'compact' => false])
@php
use App\Services\ReactionService;
$author = $post->user;
$name = $author?->displayName() ?? 'Member';
$initial = strtoupper(substr((string) $name, 0, 1));
$liked = in_array($post->id, (array) $likedIds, true);
$mine = auth()->id() && $author && (int) $author->id === (int) auth()->id();
// Prefers bulk-primed attributes (ReactionService::prime); falls back to
// row queries only when the caller did not prime (small pages).
$reactionCounts = $post->hasAttribute('reaction_counts') && is_array($post->getAttribute('reaction_counts'))
    ? $post->getAttribute('reaction_counts') : null;
if (! is_array($reactionCounts)) {
    try {
        $reactionCounts = app(ReactionService::class)->postCounts($post);
    } catch (\Throwable) {
        $reactionCounts = [];
    }
}
$myReaction = $post->hasAttribute('viewer_reaction') ? $post->getAttribute('viewer_reaction') : null;
if ($myReaction === null && ! $post->hasAttribute('viewer_reaction') && auth()->check()) {
    try {
        $myReaction = \App\Models\PostReaction::where('post_id', $post->id)->where('user_id', auth()->id())->value('type');
    } catch (\Throwable) {
    }
}
$saved = $post->hasAttribute('viewer_saved') ? (bool) $post->getAttribute('viewer_saved') : false;
if (! $saved && ! $post->hasAttribute('viewer_saved') && auth()->check()) {
    try {
        $saved = \App\Models\PostBookmark::where('post_id', $post->id)->where('user_id', auth()->id())->exists();
    } catch (\Throwable) {
    }
}
$emojis = ['like' => '♥', 'love' => '😍', 'haha' => '😄', 'wow' => '😮', 'support' => '🙌', 'interesting' => '🤔'];
$boosted = ! empty($post->boosted_until) && $post->boosted_until > now();
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
@if($author?->is_online)<span style="color:#22c55e;font-size:11px" title="Online">●</span>@endif
@if($boosted)<span class="jk-pill premium" title="Dipromosikan">Boost</span>@endif</div>
<div class="jk-muted" style="font-size:11px">{{ $post->created_at?->diffForHumans() }}{{ $post->group_id ? ' · grup' : '' }}</div>
</div>
@if(! $mine)
<details style="position:relative">
<summary class="jk-pill" style="cursor:pointer;list-style:none" aria-label="Opsi postingan">···</summary>
<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
<form method="POST" action="/komunitas/postingan/{{ $post->id }}/laporkan">@csrf<button class="jk-pill" type="submit">Laporkan</button></form>
@if($author)<form method="POST" action="/safety/block" onsubmit="return confirm('Blokir {{ $name }}?')">@csrf<input type="hidden" name="user_id" value="{{ $author->id }}"><button class="jk-pill" type="submit">Blokir penulis</button></form>@endif
<form method="POST" action="/bisukan/{{ $author?->id }}" style="display:inline">@csrf<button class="jk-pill" type="submit">Bisukan penulis</button></form>
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
@if($post->hashtags->isNotEmpty())
<div class="jk-tags" style="margin-top:8px" aria-label="Tagar">@foreach($post->hashtags as $t)<a class="jk-tag" style="text-decoration:none" href="/cari?q=%23{{ $t->slug }}">{{ $t->name }}</a>@endforeach</div>
@endif
<div style="display:flex;gap:4px;align-items:center;margin-top:10px;flex-wrap:wrap" role="group" aria-label="Reaksi">
@foreach($emojis as $type => $emoji)
@php $c = (int) ($reactionCounts[$type] ?? 0); @endphp
<form method="POST" action="/komunitas/{{ $post->id }}/reaksi" style="display:inline">@csrf<input type="hidden" name="type" value="{{ $type }}"><button class="jk-pill" type="submit" @if($myReaction === $type) style="background:#ffe4e6;border-color:#fecdd3" @endif aria-label="Reaksi {{ $type }}" aria-pressed="{{ $myReaction === $type ? 'true' : 'false' }}">{{ $emoji }}@if($c > 0) {{ $c }}@endif</button></form>
@endforeach
<form method="POST" action="/komunitas/{{ $post->id }}/simpan" style="display:inline">@csrf<button class="jk-pill" type="submit" aria-label="Simpan">{{ $saved ? '★ Tersimpan' : '☆ Simpan' }}</button></form>
<form method="POST" action="/komunitas/{{ $post->id }}/bagikan" style="display:inline">@csrf<button class="jk-pill" type="submit" aria-label="Bagikan">↗ {{ (int) ($post->shares_count ?? 0) }}</button></form>
@if($mine)
<details style="display:inline"><summary class="jk-pill" style="cursor:pointer;list-style:none" aria-label="Kelola postingan">✎</summary>
<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
<form method="POST" action="/komunitas/{{ $post->id }}" style="display:flex;gap:6px;flex:1;min-width:200px">@csrf @method('PUT')<input class="jk-input" style="flex:1" name="body" maxlength="1000" required value="{{ $post->body }}" aria-label="Ubah postingan"><button class="jk-pill" type="submit">Simpan</button></form>
<form method="POST" action="/komunitas/{{ $post->id }}/boost" style="display:inline">@csrf<button class="jk-pill" type="submit" title="Promosikan 24 jam dengan kredit">Boost</button></form>
</div>
</details>
@endif
</div>
<div class="jk-muted" style="font-size:12px;margin-top:6px">{{ $post->comments_count }} komentar</div>
@if(! $compact)
@foreach($post->comments ?? [] as $c)
<div style="margin-top:8px;padding:8px 10px;background:#fafafb;border-radius:10px;font-size:13px"><strong>{{ $c->user?->displayName() ?? 'Member' }}</strong> {{ $c->body }}
@php $replies = $c->hasAttribute('reply_list') ? $c->getAttribute('reply_list') : ($c->relationLoaded('replies') ? $c->replies : $c->replies()->with('user:id,display_name,name')->latest('id')->limit(2)->get()); @endphp
@foreach($replies as $r)<div style="margin-top:6px;padding-left:8px;border-left:2px solid #e4e4e7"><strong>{{ $r->user?->displayName() ?? 'Member' }}</strong> {{ $r->body }}</div>@endforeach
<details style="margin-top:4px"><summary class="jk-muted" style="cursor:pointer;font-size:12px">Balas</summary>
<form method="POST" action="/komunitas/{{ $post->id }}/komentar" style="display:flex;gap:6px;margin-top:6px">@csrf<input type="hidden" name="parent_id" value="{{ $c->id }}"><input class="jk-input" style="flex:1" name="body" maxlength="500" required placeholder="Balas {{ $c->user?->displayName() ?? '' }}…" aria-label="Balasan"><button class="jk-pill" type="submit">Kirim</button></form>
</details>
</div>
@endforeach
<form method="POST" action="/komunitas/{{ $post->id }}/komentar" style="display:flex;gap:6px;margin-top:8px">@csrf<input class="jk-input" style="flex:1" name="body" maxlength="500" required placeholder="Tulis komentar…" aria-label="Komentar"><button class="jk-pill" type="submit">Kirim</button></form>
@else
<a class="jk-pill" style="margin-top:8px;display:inline-block" href="/komunitas">Lihat komentar</a>
@endif
</article>
