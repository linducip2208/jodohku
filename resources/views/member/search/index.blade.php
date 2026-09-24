@extends('layouts.member')
@section('title', 'Cari — Jodohku')
@section('content')
<h1 class="jk-h1">Pencarian</h1>
<form action="/cari" method="GET" class="jk-search" role="search" style="margin-bottom:12px;max-width:520px">
<input type="search" name="q" placeholder="Cari orang, postingan, grup, event, #tagar…" aria-label="Pencarian" value="{{ $q }}" style="flex:1">
<button class="jk-btn jk-btn-like" style="flex:none;padding:10px 16px" type="submit">Cari</button>
</form>
@if($results)
@php $r = $results; @endphp
@if($r['people']->isNotEmpty())
<section class="jk-section" aria-label="Orang"><h2 class="jk-h2">Orang</h2><div class="jk-grid">@foreach($r['people'] as $u) @include('components.profile-card', ['user' => $u, 'compact' => true]) @endforeach</div></section>
@endif
@if($r['posts']->isNotEmpty())
<section class="jk-section" aria-label="Postingan"><h2 class="jk-h2">Postingan</h2>@foreach($r['posts'] as $p) @include('components.social-post', ['post' => $p, 'compact' => true]) @endforeach</section>
@endif
@if($r['groups']->isNotEmpty())
<section class="jk-section" aria-label="Grup"><h2 class="jk-h2">Komunitas</h2>@foreach($r['groups'] as $g)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $g->name }}</strong><div class="jk-muted">{{ $g->members_count }} anggota</div></div><a class="jk-pill" href="/groups/{{ $g->slug }}">Buka</a></div>@endforeach</section>
@endif
@if($r['events']->isNotEmpty())
<section class="jk-section" aria-label="Event"><h2 class="jk-h2">Event</h2>@foreach($r['events'] as $e)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $e->title }}</strong><div class="jk-muted">{{ $e->city ?? '' }}</div></div><a class="jk-pill" href="/events/{{ $e->id }}">Lihat</a></div>@endforeach</section>
@endif
@if($r['hashtags']->isNotEmpty())
<section class="jk-section" aria-label="Tagar"><h2 class="jk-h2">Tagar</h2><div class="jk-tags">@foreach($r['hashtags'] as $t)<span class="jk-tag">{{ $t->name }} · {{ $t->posts_count }}</span>@endforeach</div></section>
@endif
@if($r['people']->isEmpty() && $r['posts']->isEmpty() && $r['groups']->isEmpty() && $r['events']->isEmpty() && $r['hashtags']->isEmpty())
@include('components.empty', ['icon' => 'cari', 'title' => 'Tidak ada hasil', 'hint' => 'Coba kata kunci lain.'])
@endif
@endif
@endsection
