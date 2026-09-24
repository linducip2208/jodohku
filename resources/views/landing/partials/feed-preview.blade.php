@if(($feedPosts ?? collect())->isNotEmpty())
<section aria-label="Cuplikan komunitas" style="padding:22px 0"><div class="ld-wrap" style="max-width:640px">
<h2 class="ld-h2" style="font-size:22px">Ramai di komunitas</h2>
@foreach($feedPosts as $p)
<article class="ld-card" style="margin-top:12px;padding:16px">
<div style="display:flex;gap:10px;align-items:center">
<div class="jk-avatar sm">@if($p->user?->avatarUrl())<img src="{{ $p->user->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else{{ strtoupper(substr((string)($p->user?->displayName() ?? '?'),0,1)) }}@endif</div>
<div><strong>{{ $p->user?->displayName() ?? 'Member' }}</strong>@if($p->user?->is_verified)<span style="color:#0ea5e9" title="Terverifikasi"> ✓</span>@endif<div class="ld-muted" style="font-size:11px">{{ $p->created_at?->diffForHumans() }}</div></div>
</div>
<p style="margin:10px 0 0;font-size:14px">{{ \Illuminate\Support\Str::limit($p->body, 160) }}</p>
<div class="ld-muted" style="font-size:12px;margin-top:8px">♡ {{ $p->likes_count }} · {{ $p->comments_count }} komentar</div>
</article>
@endforeach
<div style="text-align:center;margin-top:14px"><a class="ld-btn ghost" href="{{ route('register') }}">Ikut ngobrol — daftar gratis</a></div>
</div></section>
@endif
