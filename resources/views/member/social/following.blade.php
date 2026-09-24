@extends('layouts.member')
@section('title', 'Mengikuti — Jodohku')
@section('content')
<h1 class="jk-h1">Diikuti {{ $owner->displayName() }}</h1>
<div class="jk-chat-list" role="list">
@forelse($items as $u)
<div class="jk-chat-item" role="listitem"><div class="jk-avatar">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="Foto {{ $u->displayName() }}">@else{{ strtoupper(substr((string)($u->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1"><a href="/profile/{{ $u->id }}" style="color:inherit;text-decoration:none"><strong>{{ $u->displayName() }}</strong></a><div class="jk-muted">{{ $u->city ?? '' }}</div></div>
@if((int) auth()->id() === (int) $owner->id)<form method="POST" action="/ikuti/{{ $u->id }}" style="display:inline">@csrf @method('DELETE')<button class="jk-pill" type="submit">Berhenti</button></form>@endif</div>
@empty @include('components.empty', ['icon' => 'orang', 'title' => 'Belum mengikuti siapa pun', 'hint' => 'Ikuti member agar feed-mu hidup.']) @endforelse
</div>
<div style="margin-top:12px">{{ $items->links() }}</div>
@endsection
