<section id="cerita" class="ld-section"><div class="ld-wrap">
<h2 class="ld-h2">Cerita sukses</h2><p class="ld-muted">Kisah nyata member yang dipublikasikan atas persetujuan mereka.</p>
@if(($stories ?? collect())->isEmpty())
<div class="ld-card"><p class="ld-muted" style="margin:0">Belum ada kisah yang dipublikasikan. Jadilah cerita berikutnya — <a href="{{ route('register') }}">mulai gratis</a>.</p></div>
@else
<div class="ld-grid3" style="margin-top:20px">
@foreach($stories as $s)
<div class="ld-card"><p>“{{ \Illuminate\Support\Str::limit(strip_tags((string) $s->story), 140) }}”</p><div style="font-weight:700">— {{ $s->partner_name ?: 'Pasangan Jodohku' }}</div><div class="ld-muted">{{ $s->published_at?->format('M Y') ?? '' }}</div></div>
@endforeach
</div>
@endif
</div></section>
