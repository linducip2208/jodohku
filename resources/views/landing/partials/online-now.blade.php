@if(($onlineNow ?? collect())->isNotEmpty())
<section aria-label="Online sekarang" style="padding:18px 0 4px"><div class="ld-wrap">
<div class="jk-row-scroll" role="list" style="grid-auto-columns:minmax(72px,72px)">
@foreach($onlineNow as $u)
<a role="listitem" href="{{ route('register') }}" style="text-decoration:none;text-align:center" title="{{ $u->displayName() }} — online">
<div class="jk-avatar" style="margin:0 auto;border:2px solid #22c55e">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else{{ strtoupper(substr((string)($u->displayName() ?? '?'),0,1)) }}@endif</div>
<div class="ld-muted" style="font-size:10.5px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ \Illuminate\Support\Str::limit($u->displayName() ?? 'Member', 10) }}</div>
</a>
@endforeach
</div>
</div></section>
@endif
