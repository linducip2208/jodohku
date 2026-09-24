@if(($feedPosts ?? collect())->isNotEmpty())
@php $previewPosts = $feedPosts->skip(1)->take(3); @endphp
<section aria-labelledby="community-preview-title" style="padding:22px 0"><div class="ld-wrap">
<div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:12px">
<h2 id="community-preview-title" class="ld-h2" style="font-size:22px;margin:0">Ramai di komunitas</h2>
@auth
<a href="/komunitas" style="font-size:13px;font-weight:700;color:#e11d48;text-decoration:none;white-space:nowrap">Lihat semua →</a>
@else
<a href="{{ route('register') }}" style="font-size:13px;font-weight:700;color:#e11d48;text-decoration:none;white-space:nowrap" aria-label="Lihat komunitas — daftar untuk bergabung">Lihat semua →</a>
@endauth
</div>
<div class="ld-preview-grid" role="list">
@foreach($previewPosts as $p)
<article role="listitem" class="ld-card ld-preview-card" aria-label="Postingan {{ $p->user?->displayName() ?? 'Member' }}">
<div style="display:flex;gap:8px;align-items:center">
<div class="jk-avatar sm">@if($p->user?->avatarUrl())<img src="{{ $p->user->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else{{ strtoupper(substr((string)($p->user?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="min-width:0"><strong style="font-size:13px">{{ $p->user?->displayName() ?? 'Member' }}</strong>@if($p->user?->is_verified)<span style="color:#0ea5e9" title="Terverifikasi"> ✓</span>@endif<div class="ld-muted" style="font-size:11px">{{ $p->created_at?->diffForHumans() }}</div></div>
</div>
<p style="margin:8px 0 0;font-size:13px;line-height:1.5">{{ \Illuminate\Support\Str::limit($p->body, 100) }}</p>
<div class="ld-muted" style="font-size:12px;margin-top:8px">♡ {{ $p->likes_count }} · {{ $p->comments_count }} komentar</div>
</article>
@endforeach
</div>
<div style="text-align:center;margin-top:14px"><a class="ld-btn ghost" href="{{ route('register') }}">Ikut ngobrol — daftar gratis</a></div>
</div></section>
<style>
.ld-preview-grid{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
.ld-preview-grid .ld-preview-card{margin:0;padding:14px;display:flex;flex-direction:column}
@media(max-width:899px){.ld-preview-grid{grid-template-columns:none;grid-auto-flow:column;grid-auto-columns:minmax(230px,72%);overflow-x:auto;padding-bottom:8px;scroll-snap-type:x mandatory}.ld-preview-grid>*{scroll-snap-align:start}}
</style>
@endif
