@extends('layouts.member')
@section('title', 'Komunitas — Jodohku')
@section('content')
<h1 class="jk-h1">Komunitas</h1>
<p class="jk-muted">Grup member berdasarkan minat, kota, dan tujuan.</p>
<div class="jk-section jk-form" style="padding:12px 14px;margin-bottom:12px">
<form method="POST" action="/groups">@csrf
<label class="jk-muted" style="font-size:12.5px" for="group-name">Buat komunitas baru</label>
<div style="display:flex;gap:8px;margin-top:6px">
<input id="group-name" class="jk-input" style="flex:1" name="name" maxlength="150" required placeholder="Nama komunitas…">
<button class="jk-btn jk-btn-like" style="flex:none;padding:10px 16px" type="submit">Buat</button>
</div>
</form>
</div>
@if($mine->isNotEmpty())
<section class="jk-section" aria-label="Komunitasku"><h2 class="jk-h2">Komunitasku</h2>
@foreach($mine as $g)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $g->name }}</strong><div class="jk-muted">{{ $g->members_count }} anggota</div></div><a class="jk-pill" href="/groups/{{ $g->slug }}">Buka</a></div>@endforeach
</section>
@endif
<div class="jk-grid">
@forelse($groups as $g)
<div class="jk-card"><div class="jk-card-body">
<div class="jk-name">{{ $g->name }}</div>
<div class="jk-meta">{{ $g->category ?? 'Umum' }} · {{ $g->members_count }} anggota</div>
@if($g->description)<p class="jk-muted" style="font-size:13px">{{ \Illuminate\Support\Str::limit($g->description, 100) }}</p>@endif
<div style="margin-top:8px"><a class="jk-pill" href="/groups/{{ $g->slug }}">Lihat</a></div>
</div></div>
@empty @include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada komunitas', 'hint' => 'Buat yang pertama.']) @endforelse
</div>
<div style="margin-top:12px">{{ $groups->links() }}</div>
@endsection
