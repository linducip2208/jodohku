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
$feed = null; $feedLiked = []; $storyStrip = collect();
try {
    if ($me) {
        $personal = app(PersonalizationService::class);
        $rec = $personal->getRecommendedMembers($me, 6);
        $picks = collect($rec['picks']);
        $picksUsers = $rec['users'];
        $pickIds = collect($picks)->pluck('user_id')->all();
        // Feed quality: never repeat daily picks inside "Orang untukmu".
        // NOTE: getCollection(), never collect($paginator) — the latter
        // yields the paginator's array shape, not models.
        $mayLike = app(\App\Services\DiscoveryService::class)->discover($me, ['sort' => 'compatibility'], 8)->getCollection()
            ->reject(fn ($u) => in_array($u->id, $pickIds))->take(4)->values();
        $newMembers = $personal->getNewMembers(6);
        $activeNow = $personal->getActiveNow($me, 6);
        $journey = \App\Models\Courtship::where(fn ($q) => $q->where('initiator_id', $me->id)->orWhere('partner_id', $me->id))->where('status', 'active')->latest('id')->first();
        $events = $personal->getRecommendedEvents($me, 3);
        $forums = $personal->getRecommendedForums($me, 3);
        $stories = SuccessStory::where('status', 'published')->latest('published_at')->latest('id')->limit(2)->get();
        $posts = BlogPost::published()->latest('published_at')->limit(3)->get();
        // Unified social feed: public, visible, non-blocked community posts.
        $blockedIds = \App\Models\Block::where('blocker_id', $me->id)->pluck('blocked_id')
            ->merge(\App\Models\Block::where('blocked_id', $me->id)->pluck('blocker_id'))->all();
        $feed = \App\Models\Post::where('is_hidden', false)
            ->when($blockedIds, fn ($q) => $q->whereNotIn('user_id', $blockedIds))
            ->with(['user:id,display_name,name,avatar_path,is_verified,is_online', 'comments' => fn ($q) => $q->latest('id')->limit(2)->with('user:id,display_name,name')])
            ->withCount(['comments', 'likes'])->latest('id')->paginate(8);
        $feedLiked = \App\Models\PostLike::where('user_id', $me->id)->whereIn('post_id', $feed->getCollection()->pluck('id'))->pluck('post_id')->all();
        // Stories strip from real entities (no ephemeral story system).
        $storyStrip = $activeNow->take(4)->map(fn ($u) => ['user' => $u, 'label' => 'Online'])
            ->merge($newMembers->take(4)->map(fn ($u) => ['user' => $u, 'label' => 'Baru']))
            ->unique(fn ($s) => $s['user']->id)->take(8)->values();
    }
} catch (\Throwable) {}
@endphp
<div class="jk-feed">
<div style="padding:4px 2px 0">
<div class="jk-muted" style="font-size:12.5px">{{ $greet }},</div>
<h1 class="jk-h1" style="font-size:20px">{{ $me?->displayName() ?? 'kamu' }}</h1>
</div>

@if($storyStrip->isNotEmpty())
<div class="jk-row-scroll" role="list" aria-label="Sorotan" style="grid-auto-columns:minmax(72px,72px)">
@foreach($storyStrip as $s)
<a role="listitem" href="/profile/{{ $s['user']->id }}" style="text-decoration:none;text-align:center">
<div class="jk-avatar" style="margin:0 auto;border:2px solid #f43f5e">@if($s['user']->avatarUrl())<img src="{{ $s['user']->avatarUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else{{ strtoupper(substr((string)($s['user']->displayName() ?? '?'),0,1)) }}@endif</div>
<div class="jk-muted" style="font-size:10px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $s['label'] }}</div>
</a>
@endforeach
</div>
@endif

<div class="jk-section jk-form" style="padding:12px 14px">
<form method="POST" action="/komunitas">@csrf
<label class="jk-muted" style="font-size:12.5px" for="home-post">Bagikan sesuatu…</label>
<div style="display:flex;gap:8px;margin-top:6px">
<input id="home-post" class="jk-input" style="flex:1" name="body" maxlength="1000" required placeholder="Ceritakan harimu, tanya komunitas…">
<button class="jk-btn jk-btn-like" style="flex:none;padding:10px 16px" type="submit">Kirim</button>
</div>
</form>
</div>

@if($feed && $feed->count())
@foreach($feed as $p)
@include('components.social-post', ['post' => $p, 'likedIds' => $feedLiked, 'compact' => true])
@endforeach
<div>{{ $feed->links() }}</div>
@else
@include('components.empty', ['icon' => 'chat', 'title' => 'Belum ada postingan di feed Anda', 'hint' => 'Tulis postingan pertama atau temukan orang baru di Discover.'])
@endif

<section class="jk-section" aria-labelledby="h-maylike">
<h2 class="jk-h2" id="h-maylike">Orang untukmu</h2>
@if($mayLike->isEmpty())
@include('components.empty', ['icon' => 'hati', 'title' => 'Belum ada saran', 'hint' => 'Coba longgarkan filter di Discover.'])
@else
<div class="jk-grid">@foreach($mayLike as $p) @include('components.profile-card', ['user' => $p, 'score' => $p->compatibility_score ?? null]) @endforeach</div>
@endif
</section>

<section class="jk-section" aria-labelledby="h-daily">
<h2 class="jk-h2" id="h-daily">Rekomendasi harian</h2>
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

@if($journey)
@php
$stages = \App\Enums\CourtshipStage::cases();
$currentIdx = array_search($journey->stage, $stages);
$pct = (int) (($currentIdx + 1) / max(1, count($stages)) * 100);
@endphp
<section class="jk-section" aria-labelledby="h-journey" style="border-left:4px solid #8b5cf6">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<div style="flex:1;min-width:160px"><div class="jk-h2" id="h-journey" style="margin:0">Taaruf: {{ $journey->stage->label() }}</div>
<div class="jk-progress" style="margin-top:8px"><div style="width:{{ $pct }}%"></div></div></div>
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center;flex:none;padding:10px 16px" href="/biro-jodoh/taaruf/{{ $journey->id }}">Lanjut</a>
</div>
</section>
@endif

<section class="jk-section" aria-labelledby="h-maylike">
<h2 class="jk-h2" id="h-maylike">Orang untukmu</h2>

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
