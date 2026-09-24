<section class="ld-hero"><div class="ld-wrap ld-hero-grid">
<div>
<div class="ld-card" style="display:inline-block;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;color:#be123c;background:#fff1f2;border-color:#fecdd3">{{ number_format($stats['members'] ?? 0) }} member aktif</div>
<h1>Jodohku</h1>
<p class="ld-sub" style="font-size:19px;font-weight:600;color:#18181b">Temukan orang yang cocok. Kenali. Ngobrol. Bangun hubungan.</p>
<p class="ld-sub">Matching berbasis kepribadian, minat &amp; tujuan hubungan. Chat realtime, profil terverifikasi, dan event seru di kotamu. Gratis untuk memulai.</p>
<div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
<a class="ld-btn" href="{{ route('register') }}">Mulai Gratis →</a>
<a class="ld-btn ghost" href="#demo">Lihat Demo Member</a>
</div>
<div style="display:flex;gap:18px;margin-top:18px;font-size:13px;color:#52525b;flex-wrap:wrap">
<span>{{ number_format($stats['verified'] ?? 0) }} profil terverifikasi</span><span>{{ number_format($stats['matches'] ?? 0) }} match aktif</span><span>Chat realtime</span>
</div>
</div>
@php
$heroMember = ($demoMembers ?? collect())->first(fn ($m) => ! empty($m->avatarUrl()));
$heroPost = ($feedPosts ?? collect())->first();
@endphp
<div class="ld-phones" aria-label="Cuplikan aplikasi">
@if($heroMember)
<a href="{{ route('register') }}" style="text-decoration:none;color:inherit" aria-label="Lihat profil {{ $heroMember->displayName() }} — daftar untuk mulai"><div class="ld-phone"><div>
<div style="font-weight:800">{{ $heroMember->displayName() }}{{ $heroMember->age() ? ', '.$heroMember->age() : '' }}@if($heroMember->is_verified) ✓@endif</div>
@if($heroMember->avatarUrl())<img src="{{ $heroMember->avatarUrl() }}" alt="" loading="lazy" style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:10px;margin-top:6px" onerror="this.remove()">@endif
<div style="margin-top:6px;background:#fff1f2;border-radius:10px;padding:8px">{{ $heroMember->city ?: 'Indonesia' }}{{ $heroMember->profile?->headline ? ' · '.\Illuminate\Support\Str::limit($heroMember->profile->headline, 40) : '' }}</div>
<div style="margin-top:8px;display:flex;gap:6px"><span style="background:#f43f5e;color:#fff;border-radius:8px;padding:6px 10px">❤️ Like</span><span style="background:#f4f4f5;border-radius:8px;padding:6px 10px">💬 Chat</span></div>
</div></div></a>
@endif
@if($heroPost)
<a href="{{ route('register') }}" style="text-decoration:none;color:inherit" aria-label="Ikut diskusi komunitas — daftar untuk mulai"><div class="ld-phone"><div>
<div style="font-weight:800">💬 Komunitas</div>
<div style="margin-top:6px"><strong>{{ $heroPost->user?->displayName() ?? 'Member' }}</strong></div>
<div style="margin-top:6px;background:#f4f4f5;border-radius:12px;padding:8px;font-size:11px">"{{ \Illuminate\Support\Str::limit($heroPost->body, 90) }}"</div>
<div style="margin-top:6px;font-size:11px;color:#52525b">♡ {{ $heroPost->likes_count }} · {{ $heroPost->comments_count }} komentar</div>
</div></div></a>
@endif
<a href="{{ route('register') }}" style="text-decoration:none;color:inherit" aria-label="Mulai taaruf — daftar gratis"><div class="ld-phone"><div>
<div style="font-weight:800">💘 Smart Taaruf</div>
<div style="font-size:28px;margin:8px 0" aria-hidden="true">🤝</div>
<div>Kenalan → taaruf → khitbah, didampingi wali &amp; konselor.</div>
<div style="margin-top:8px;background:#18181b;color:#fff;border-radius:10px;padding:8px;text-align:center">Mulai Gratis</div>
</div></div></a>
</div>
</div></section>
