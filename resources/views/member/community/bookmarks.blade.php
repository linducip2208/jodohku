@extends('layouts.member')
@section('title', 'Tersimpan — Jodohku')
@section('content')
<h1 class="jk-h1">Tersimpan</h1>
<p class="jk-muted">Postingan yang kamu simpan — hanya kamu yang bisa melihat.</p>
<div class="jk-feed">
@forelse($items as $b)
@if($b->post) @include('components.social-post', ['post' => $b->post, 'compact' => true]) @endif
@empty @include('components.empty', ['icon' => 'simpan', 'title' => 'Belum ada simpanan', 'hint' => 'Simpan postingan menarik untuk dibaca lagi.']) @endforelse
</div>
<div style="margin-top:12px">{{ $items->links() }}</div>
@endsection
