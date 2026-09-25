@extends('layouts.member')
@section('title', 'Discover — Jodohku')
@section('content')
<h1 class="jk-h1">Discover</h1>
<p class="jk-muted">Orang di sekitarmu — rekomendasi berbasis kecocokan, bukan katalog.</p>
@php $tab = request('tab', 'orang'); @endphp
<div class="jk-tabs" role="tablist" aria-label="Kategori discovery">
@foreach(['orang' => 'Orang', 'postingan' => 'Postingan', 'komunitas' => 'Komunitas', 'event' => 'Event', 'trending' => 'Trending'] as $k => $label)
<a class="jk-tab {{ $tab === $k ? 'active' : '' }}" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" href="/discover?tab={{ $k }}">{{ $label }}</a>
@endforeach
</div>
@if($tab === 'orang')
<p class="jk-muted">Rekomendasi berbasis kecocokan, bukan katalog.</p>
@livewire('discover-grid')
@elseif($tab === 'postingan')
@php
$trendingPosts = collect();
try { $trendingPosts = app(\App\Services\FeedService::class)->trending(auth()->user(), 10); } catch (\Throwable) {}
@endphp
<div class="jk-feed">
@forelse($trendingPosts as $p) @include('components.social-post', ['post' => $p, 'compact' => true]) @empty @include('components.empty', ['icon' => 'cerah', 'title' => 'Belum ada yang trending', 'hint' => 'Postingan dengan interaksi tinggi akan muncul di sini.']) @endforelse
</div>
@elseif($tab === 'komunitas')
@php
$groups = collect();
try { $groups = \App\Models\Group::visibleTo(auth()->user())->withCount('members')->orderByDesc('members_count')->limit(12)->get(); } catch (\Throwable) {}
@endphp
<div class="jk-grid">
@forelse($groups as $g)
<div class="jk-card"><div class="jk-card-body"><div class="jk-name">{{ $g->name }}</div><div class="jk-meta">{{ $g->category ?? 'Umum' }} · {{ $g->members_count }} anggota</div><div style="margin-top:8px"><a class="jk-pill" href="/groups/{{ $g->slug }}">Lihat</a></div></div></div>
@empty @include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada komunitas', 'hint' => 'Buat komunitas pertamamu.']) @endforelse
</div>
@elseif($tab === 'event')
@php
$events = collect();
try { $events = \App\Models\Event::where('status', 'published')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(12)->get(); } catch (\Throwable) {}
@endphp
@forelse($events as $e)
<div class="jk-story" style="margin-bottom:10px"><div style="flex:1"><strong>{{ $e->title }}</strong><div class="jk-muted">{{ $e->starts_at?->format('d M Y H:i') }}{{ $e->city ? ' · '.$e->city : '' }}</div></div><a class="jk-pill" href="/events/{{ $e->id }}">Lihat</a></div>
@empty @include('components.empty', ['icon' => 'kalender', 'title' => 'Belum ada event', 'hint' => 'Event mendatang akan muncul di sini.']) @endforelse
@else
@php
$tags = [];
try { $tags = app(\App\Services\HashtagService::class)->trending(12); } catch (\Throwable) {}
@endphp
@if($tags)
<section class="jk-section" aria-label="Tagar trending"><h2 class="jk-h2">Tagar trending</h2><div class="jk-tags">@foreach($tags as $t)<a class="jk-tag" style="text-decoration:none" href="/cari?q=%23{{ $t['slug'] }}">{{ $t['name'] }} · {{ $t['posts_count'] }}</a>@endforeach</div></section>
@endif
<p class="jk-muted">Jelajahi <a href="/cari">pencarian</a> untuk orang, postingan, dan topik.</p>
@endif
@endsection
