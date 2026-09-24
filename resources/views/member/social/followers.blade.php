@extends('layouts.member')
@section('title', 'Pengikut — Jodohku')
@section('content')
<h1 class="jk-h1">Pengikut {{ $owner->displayName() }}</h1>
<div class="jk-chat-list" role="list">
@forelse($items as $u)
<div class="jk-chat-item" role="listitem"><div class="jk-avatar">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="Foto {{ $u->displayName() }}">@else{{ strtoupper(substr((string)($u->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1"><a href="/profile/{{ $u->id }}" style="color:inherit;text-decoration:none"><strong>{{ $u->displayName() }}</strong></a><div class="jk-muted">{{ $u->city ?? '' }}</div></div></div>
@empty @include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada pengikut', 'hint' => 'Bagikan profil agar ditemukan.']) @endforelse
</div>
<div style="margin-top:12px">{{ $items->links() }}</div>
@endsection
