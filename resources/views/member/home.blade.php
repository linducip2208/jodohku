@extends('layouts.member')
@section('title', 'Home — Jodohku')
@section('content')
@livewire('profile-completeness')
@php
use App\Models\BlogPost;
use App\Models\SuccessStory;
use App\Services\PersonalizationService;
$greet = now()->hour < 11 ? 'Selamat pagi' : (now()->hour < 15 ? 'Selamat siang' : (now()->hour < 19 ? 'Selamat sore' : 'Selamat malam'));
$me = auth()->user();
$picks = collect(); $picksUsers = collect(); $mayLike = collect(); $newMembers = collect(); $activeNow = collect();
$journey = null; $events = collect(); $stories = collect(); $posts = collect(); $forums = collect();
try {
    if ($me) {
        $personal = app(PersonalizationService::class);
        $rec = $personal->getRecommendedMembers($me, 6);
        $picks = collect($rec['picks']);
        $picksUsers = $rec['users'];
        $pickIds = collect($picks)->pluck('user_id')->all();
        // Feed quality: never repeat daily picks inside "Mungkin kamu suka".
        $mayLike = collect(app(\App\Services\DiscoveryService::class)->discover($me, ['sort' => 'compatibility'], 8))
            ->reject(fn ($u) => in_array($u->id, $pickIds))->take(4)->values();
        $newMembers = $personal->getNewMembers(6);
        $activeNow = $personal->getActiveNow($me, 6);
        $journey = \App\Models\Courtship::where(fn ($q) => $q->where('initiator_id', $me->id)->orWhere('partner_id', $me->id))->where('status', 'active')->latest('id')->first();
        $events = $personal->getRecommendedEvents($me, 3);
        $forums = $personal->getRecommendedForums($me, 3);
        $stories = SuccessStory::where('status', 'published')->latest('published_at')->latest('id')->limit(2)->get();
        $posts = BlogPost::published()->latest('published_at')->limit(3)->get();
    }
} catch (\Throwable) {}
@endphp
<div class="jk-feed">
<div class="jk-hero">
<h1 class="jk-h1">{{ $greet }}, {{ $me?->displayName() ?? 'kamu' }}</h1>
<p class="jk-greet">Temukan seseorang yang sejalan dengan nilaimu.</p>
<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center" href="/discover">Jelajahi</a>
<a class="jk-btn jk-btn-super" style="text-decoration:none;text-align:center" href="/boosts">Boost profil</a>
<a class="jk-btn jk-btn-fav" style="text-decoration:none;text-align:center" href="/premium">Premium</a>
</div>
</div>

<section class="jk-section" aria-labelledby="h-daily">
<h2 class="jk-h2" id="h-daily">Kecocokan harianmu</h2>
@if($picks->isEmpty())
@include('components.empty', ['icon' => 'cerah', 'title' => 'Belum ada rekomendasi hari ini', 'hint' => 'Lengkapi profil dan jawab kuesioner untuk rekomendasi yang lebih tepat.'])
@else
<div class="jk-row-scroll">
@foreach($picks as $p)
@php $u = $picksUsers->get($p['user_id'] ?? 0); @endphp
@if($u) @include('components.profile-card', ['user' => $u, 'score' => $p['compatibility_score'] ?? null, 'compact' => true]) @endif
@endforeach
</div>
@endif
</section>

<section class="jk-section" aria-labelledby="h-maylike">
<h2 class="jk-h2" id="h-maylike">Mungkin kamu suka</h2>
@if($mayLike->isEmpty())
@include('components.empty', ['icon' => 'hati', 'title' => 'Belum ada saran', 'hint' => 'Coba longgarkan filter di Discover.'])
@else
<div class="jk-grid">@foreach($mayLike as $p) @include('components.profile-card', ['user' => $p, 'score' => $p->compatibility_score ?? null]) @endforeach</div>
@endif
</section>

@if($journey)
<section class="jk-section" aria-labelledby="h-journey">
<h2 class="jk-h2" id="h-journey">Perjalanan taarufmu</h2>
<p class="jk-muted">Tahap saat ini: <strong>{{ $journey->stage->label() }}</strong></p>
@php
$stages = \App\Enums\CourtshipStage::cases();
$currentIdx = array_search($journey->stage, $stages);
@endphp
<ol class="jk-journey">
@foreach($stages as $i => $s)
<li class="{{ $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'now' : '') }}"><div><div class="t">{{ $s->label() }}</div><div class="d">{{ $i < $currentIdx ? 'Selesai' : ($i === $currentIdx ? 'Berlangsung' : 'Berikutnya') }}</div></div></li>
@endforeach
</ol>
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center;margin-top:12px" href="/biro-jodoh/taaruf/{{ $journey->id }}">Lanjutkan perjalanan</a>
</section>
@endif

<section class="jk-section" aria-labelledby="h-new">
<h2 class="jk-h2" id="h-new">Anggota baru</h2>
@if($newMembers->isEmpty())
@include('components.empty', ['icon' => 'baru', 'title' => 'Belum ada anggota baru', 'hint' => 'Cek lagi nanti.'])
@else
<div class="jk-row-scroll">@foreach($newMembers as $u) @include('components.profile-card', ['user' => $u, 'compact' => true]) @endforeach</div>
@endif
</section>

<section class="jk-section" aria-labelledby="h-active">
<h2 class="jk-h2" id="h-active">Sedang aktif</h2>
@if($activeNow->isEmpty())
<p class="jk-muted">Tidak ada yang online saat ini. Coba lagi nanti.</p>
@else
<div class="jk-row-scroll">@foreach($activeNow as $u) @include('components.profile-card', ['user' => $u, 'compact' => true]) @endforeach</div>
@endif
</section>

@if($events->isNotEmpty())
<section class="jk-section" aria-labelledby="h-events">
<h2 class="jk-h2" id="h-events">Acara terdekat</h2>
@foreach($events as $e)
<div class="jk-story" style="margin-bottom:10px"><div style="flex:1"><strong>{{ $e->title }}</strong><div class="jk-muted">{{ $e->starts_at?->format('d M Y H:i') }}{{ $e->city ? ' · '.$e->city : '' }}</div></div><a class="jk-pill" href="/events/{{ $e->id }}">Lihat</a></div>
@endforeach
</section>
@endif

@if($stories->isNotEmpty())
<section class="jk-section" aria-labelledby="h-story">
<h2 class="jk-h2" id="h-story">Kisah sukses</h2>
@foreach($stories as $s)
<div class="jk-story" style="margin-bottom:10px"><div style="flex:1"><strong>{{ $s->partner_name ? 'Kisah '.$s->partner_name : 'Kisah mereka' }}</strong><div class="jk-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string)($s->story ?? '')), 110) }}</div></div><a class="jk-pill" href="/biro-jodoh/kisah">Baca</a></div>
@endforeach
</section>
@endif

@if($forums->isNotEmpty())
<section class="jk-section" aria-labelledby="h-forums">
<h2 class="jk-h2" id="h-forums">Diskusi untukmu</h2>
@foreach($forums as $f)
<div class="jk-story" style="margin-bottom:10px"><div style="flex:1"><strong>{{ $f->name }}</strong><div class="jk-muted">{{ $f->visible_threads_count ?? $f->threads_count ?? 0 }} diskusi{{ ($f->recommend_score ?? 0) > 0 ? ' · sesuai minatmu' : '' }}</div></div><a class="jk-pill" href="/forums/{{ $f->slug }}">Buka</a></div>
@endforeach
</section>
@endif

@if($posts->isNotEmpty())
<section class="jk-section" aria-labelledby="h-posts">
<h2 class="jk-h2" id="h-posts">Bacaan taaruf</h2>
@foreach($posts as $p)
<div style="margin-bottom:10px"><a href="/blog/{{ $p->slug }}"><strong>{{ $p->title }}</strong></a><div class="jk-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string)($p->excerpt ?? '')), 100) }}</div></div>
@endforeach
</section>
@endif

<section class="jk-section" aria-labelledby="h-safe">
<h2 class="jk-h2" id="h-safe">Tetap aman</h2>
<p class="jk-muted">Jangan kirim uang atau kode OTP kepada siapa pun. Laporkan perilaku mencurigakan.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><a class="jk-pill" href="/safety">Pusat Keamanan</a><a class="jk-pill" href="/settings">Pengaturan privasi</a></div>
</section>
</div>
@endsection

@section('sidebar')
<div class="jk-section"><div class="jk-h2">Profilmu</div>@livewire('profile-completeness')</div>
<div class="jk-section"><div class="jk-h2">Chat</div><p class="jk-muted">Lanjut ngobrol dengan match.</p><a href="/chat">Buka inbox →</a></div>
<div class="jk-section"><div class="jk-h2">Butuh pendamping?</div><p class="jk-muted">Konselor siap membantu perjalanan taarufmu.</p><a href="/biro-jodoh/konselor">Lihat konselor →</a></div>
@endsection
