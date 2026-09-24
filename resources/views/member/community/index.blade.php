@extends('layouts.member')
@section('title', 'Komunitas — Jodohku')
@section('content')
<h1 class="jk-h1">Komunitas</h1>
<p class="jk-muted">Cerita dan diskusi member — dimoderasi. <a href="/forums">Forum diskusi</a> · <a href="/events">Events</a></p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section jk-form">
<form method="POST" action="/komunitas">@csrf
<label for="post-body">Bagikan sesuatu…</label>
<textarea id="post-body" name="body" rows="3" maxlength="1000" required placeholder="Cerita taaruf, tips, atau pertanyaan untuk komunitas…"></textarea>
<button class="jk-submit" style="margin-top:10px" type="submit">Posting</button>
</form>
</div>
<div class="jk-feed">
@forelse($posts as $p)
@include('components.social-post', ['post' => $p, 'likedIds' => $likedIds ?? []])
@empty
@include('components.empty', ['icon' => 'chat', 'title' => 'Belum ada postingan di feed Anda', 'hint' => 'Jadilah yang pertama berbagi cerita atau temukan orang baru.'])
@endforelse
</div>
<div style="margin-top:12px">{{ $posts->links() }}</div>
@endsection
