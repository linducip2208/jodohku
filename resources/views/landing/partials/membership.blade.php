<section id="membership" class="ld-section alt"><div class="ld-wrap">
<h2 class="ld-h2">Membership simpel &amp; jujur</h2><p class="ld-muted">Mulai gratis. Upgrade kapan saja. Batalkan kapan saja.</p>
@php
$freeLikes = config('jodohku.limits.free_daily_likes', 20);
$freeSuper = config('jodohku.limits.free_daily_super_likes', 1);
$premium = ($plans ?? collect())->firstWhere('code', 'premium_monthly');
$vip = ($plans ?? collect())->firstWhere('code', 'vip_monthly');
$fmt = fn ($p) => $p ? 'Rp'.number_format((float) $p->price, 0, ',', '.') : '—';
@endphp
<div class="ld-grid3" style="margin-top:20px">
<div class="ld-card"><h3>Gratis</h3><div class="ld-price">Rp0</div><p class="ld-muted">{{ $freeLikes }} like/hari · {{ $freeSuper }} superlike/hari · chat dengan match · event publik</p><a class="ld-btn ghost" href="{{ route('register') }}">Daftar</a></div>
<div class="ld-card" style="border:2px solid #f43f5e"><h3>Premium</h3><div class="ld-price">{{ $fmt($premium) }}<span style="font-size:14px;color:#71717a">/bln</span></div><p class="ld-muted">Like tanpa batas · lihat siapa yang like · filter lanjutan · rewind · incognito · prioritas Discover</p>@auth<a class="ld-btn" href="/premium">Pilih Premium</a>@else<a class="ld-btn" href="{{ route('register') }}">Daftar untuk Premium</a>@endauth</div>
<div class="ld-card"><h3>VIP</h3><div class="ld-price">{{ $fmt($vip) }}<span style="font-size:14px;color:#71717a">/bln</span></div><p class="ld-muted">Semua Premium + prioritas matchmaker AI + pendampingan operator.</p>@auth<a class="ld-btn ghost" href="/premium">Pilih VIP</a>@else<a class="ld-btn ghost" href="{{ route('register') }}">Daftar untuk VIP</a>@endauth</div>
</div></div></section>
