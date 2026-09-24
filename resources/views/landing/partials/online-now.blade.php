@if(($onlineNow ?? collect())->isNotEmpty())
<section aria-labelledby="online-now-title" style="padding:18px 0 4px"><div class="ld-wrap">
<h2 id="online-now-title" class="ld-h2" style="font-size:20px;margin:0 0 10px">Online sekarang <span style="font-size:12px;font-weight:600;color:#16a34a">● {{ $onlineNow->count() }} orang</span></h2>
<div class="jk-row-scroll" role="list" style="grid-auto-columns:minmax(72px,72px)">
@foreach($onlineNow as $u)
<a role="listitem" href="{{ route('register') }}" style="text-decoration:none;text-align:center" title="{{ $u->displayName() }} — online" aria-label="{{ $u->displayName() }}, online sekarang. Daftar untuk menyapa.">
<div class="jk-avatar" style="margin:0 auto;border:2px solid #22c55e">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else{{ strtoupper(substr((string)($u->displayName() ?? '?'),0,1)) }}@endif</div>
<div class="ld-muted" style="font-size:10.5px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#18181b;font-weight:700">{{ \Illuminate\Support\Str::limit($u->displayName() ?? 'Member', 10) }}</div>
<div style="font-size:9.5px;color:#16a34a;font-weight:700">● Online</div>
</a>
@endforeach
</div>
</div></section>
@endif
