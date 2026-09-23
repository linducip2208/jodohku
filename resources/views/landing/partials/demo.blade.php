<section id="demo" class="ld-section"><div class="ld-wrap">
<h2 class="ld-h2">Lihat demo member</h2>
<p class="ld-muted">Akun demo fiktif untuk mencoba tampilan — data privat member asli tidak pernah ditampilkan di sini.</p>
@if(($demoMembers ?? collect())->isEmpty())
<div class="ld-card"><p class="ld-muted" style="margin:0">Demo member belum tersedia. Jalankan <code>php artisan jodohku:demo --users=5000</code> lalu muat ulang.</p></div>
@else
<div class="ld-grid3" style="margin-top:20px">
@foreach($demoMembers as $m)
@php $photo = $m->photos->first(); @endphp
<div class="ld-card" style="padding:0;overflow:hidden">
@if($photo)<img src="{{ asset('storage/'.$photo->path) }}" alt="Foto demo {{ $m->displayName() }}" width="400" height="400" loading="lazy" style="width:100%;height:220px;object-fit:cover;display:block" onerror="this.style.display='none'">@endif
<div style="padding:16px">
<div style="font-weight:800">{{ $m->displayName() }}@if($m->age()), {{ $m->age() }}@endif @if($m->is_verified)<span title="Terverifikasi" style="color:#0ea5e9">✓</span>@endif @if($m->is_online)<span title="Online" style="color:#22c55e;font-size:12px">●</span>@endif</div>
<div class="ld-muted" style="font-size:13px">{{ $m->city ?? 'Indonesia' }}{{ $m->profile?->occupation ? ' · '.$m->profile->occupation : '' }}</div>
@if($m->profile?->headline)<p style="font-size:13.5px;margin:8px 0 0">“{{ \Illuminate\Support\Str::limit($m->profile->headline, 80) }}”</p>@endif
@if($m->interests->isNotEmpty())<p class="ld-muted" style="font-size:12px;margin:8px 0 0">{{ $m->interests->pluck('name')->take(3)->implode(' · ') }}</p>@endif
<a class="ld-btn ghost" style="margin-top:10px;font-size:13px;padding:8px 16px" href="{{ route('register') }}">Sapa {{ $m->displayName() }}</a>
</div>
</div>
@endforeach
</div>
@endif
</div></section>
