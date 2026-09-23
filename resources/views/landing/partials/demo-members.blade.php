@php
use App\Models\User;
use Illuminate\Support\Facades\Cache;

$demoMembers = collect();
try {
    $demoMembers = Cache::remember('landing:demo-members', 3600, fn () => User::query()
        ->where('is_demo', true)
        ->where('status', 'active')
        ->whereHas('profile')
        ->with([
            'profile',
            'photos' => fn ($q) => $q->ordered()
                ->where('status', 'approved')
                ->where('is_private', false),
            'interests',
        ])
        ->orderByDesc('is_verified')
        ->orderByDesc('is_premium')
        ->orderByDesc('last_active_at')
        ->limit(12)
        ->get());
} catch (\Throwable) {
    $demoMembers = collect();
}
@endphp

<section id="demo" aria-labelledby="demo-members-title" style="padding:34px 0 18px;background:#fafafa">
<div class="ld-wrap">
<div style="display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap;margin-bottom:16px">
<div>
<div style="display:inline-block;padding:5px 10px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:800;letter-spacing:.04em">LIVE DEMO</div>
<h2 id="demo-members-title" style="font-size:30px;line-height:1.15;margin:8px 0 6px">Lihat contoh member Jodohku</h2>
<p style="margin:0;color:#71717a;font-size:14px">Ini data demo untuk memperlihatkan pengalaman frontend. Profil demo dibuat khusus untuk showcase dan bukan data pengguna nyata.</p>
</div>
<a class="ld-btn ghost" href="{{ route('register') }}">Coba sendiri →</a>
</div>

@if($demoMembers->isEmpty())
<div class="ld-card" style="padding:22px;text-align:center;color:#71717a">
<strong>Demo member belum tersedia.</strong>
<div style="font-size:13px;margin-top:4px">Jalankan <code>php artisan jodohku:demo --users=5000</code> pada environment demo/staging.</div>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
@foreach($demoMembers as $member)
@php
    $name = $member->displayName();
    $age = $member->age();
    $photo = $member->avatarUrl();
    $initial = strtoupper(substr((string)$name, 0, 1));
    $interests = collect($member->interests ?? [])->pluck('name')->filter()->take(3);
@endphp
<article class="ld-card" style="overflow:hidden;padding:0">
<div style="position:relative;aspect-ratio:3/4;background:linear-gradient(135deg,#ffe4e6,#fce7f3,#ede9fe)">
@if($photo)
<img src="{{ $photo }}" alt="Foto {{ $name }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
@else
<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:46px;font-weight:800;color:#fff;background:linear-gradient(135deg,#f43f5e,#8b5cf6)">{{ $initial }}</div>
@endif
@if($member->is_verified)
<span style="position:absolute;top:9px;left:9px;background:#fff;color:#0369a1;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:800">✓ VERIFIED</span>
@endif
@if($member->is_online)
<span style="position:absolute;top:10px;right:10px;width:12px;height:12px;border-radius:50%;background:#22c55e;border:2px solid #fff" title="Online"></span>
@endif
</div>
<div style="padding:12px">
<div style="font-weight:800;font-size:15px">{{ $name }}@if($age), {{ $age }}@endif</div>
<div style="font-size:12px;color:#71717a;margin-top:3px">{{ $member->city ?: 'Indonesia' }}{{ $member->profile?->occupation ? ' · '.$member->profile->occupation : '' }}</div>
@if($member->profile?->headline)
<div style="font-size:12.5px;margin-top:7px;line-height:1.4">{{ \Illuminate\Support\Str::limit($member->profile->headline, 70) }}</div>
@endif
@if($interests->isNotEmpty())
<div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:8px">
@foreach($interests as $interest)<span style="font-size:10px;background:#f4f4f5;border-radius:999px;padding:3px 7px;color:#52525b">{{ $interest }}</span>@endforeach
</div>
@endif
<div style="margin-top:10px">
<a class="ld-btn ghost" style="display:block;text-align:center" href="{{ route('register') }}">Lihat & Daftar</a>
</div>
</div>
</article>
@endforeach
</div>
@endif
</div>
</section>

<style>
@media(min-width:640px){
#demo .ld-wrap>div:nth-child(2){grid-template-columns:repeat(3,minmax(0,1fr))!important}
}
@media(min-width:1024px){
#demo .ld-wrap>div:nth-child(2){grid-template-columns:repeat(6,minmax(0,1fr))!important}
}
</style>
