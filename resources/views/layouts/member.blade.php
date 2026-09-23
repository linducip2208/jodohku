<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Jodohku')</title>
<meta name="description" content="Jodohku — temukan pasangan yang sejalan nilai dan tujuan pernikahan.">
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="{{ app(\App\Services\SeoService::class)->canonical() }}">
<meta name="theme-color" content="#f43f5e">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="/favicon.ico">
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
@vite(['resources/css/app.css', 'resources/css/member.css', 'resources/js/app.js'])
@else
<style>body{margin:0;font-family:'Instrument Sans',system-ui,sans-serif;background:#fafafb;color:#18181b}</style>
@endif
<style>body{margin:0;font-family:'Instrument Sans',system-ui,sans-serif;background:#fafafb;color:#18181b}</style>
@livewireStyles
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>try{var t=localStorage.getItem('jk-theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}</script>
</head>
<body class="jk-body" x-data="{ sidebar:false, confirm:{open:false,title:'',text:'',action:''} }">
<a href="#jk-content" class="jk-skip">Lewati ke konten</a>
@php
$unreadChats = 0; $unreadNotifs = 0;
try {
    if (auth()->check()) {
        $me = auth()->id();
        $unreadChats = \App\Models\ConversationMember::where('conversation_members.user_id', $me)->whereNull('conversation_members.left_at')
            ->whereExists(function ($q) use ($me) {
                $q->selectRaw('1')->from('messages as m')
                    ->whereColumn('m.conversation_id', 'conversation_members.conversation_id')
                    ->where('m.sender_id', '!=', $me)->whereNull('m.deleted_at')
                    ->where(fn ($qq) => $qq->whereNull('conversation_members.last_read_at')->orWhereColumn('m.created_at', '>', 'conversation_members.last_read_at'));
            })->count();
        $unreadNotifs = auth()->user()->unreadNotifications()->count();
    }
} catch (\Throwable) {}
@endphp
<div class="jk-app">
<a href="#jk-content" class="jk-skip">Lewati ke konten utama</a>
<aside class="jk-sidebar" style="display:block" x-show="true">
<div class="jk-logo" style="padding:4px 12px 16px">Jodoh<span style="background:linear-gradient(135deg,#f43f5e,#8b5cf6);-webkit-background-clip:text;background-clip:text;color:transparent">ku</span></div>
<nav aria-label="Navigasi desktop">
<a class="jk-side-link {{ request()->is('home') ? 'active' : '' }}" href="/home"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z"/></svg> Home</a>
<a class="jk-side-link {{ request()->is('discover*') ? 'active' : '' }}" href="/discover"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg> Discover</a>
<a class="jk-side-link {{ request()->is('matches*') ? 'active' : '' }}" href="/matches"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7.5-4.7-9.5-9C1 8.5 3 5 6.5 5c2 0 3.5 1 4.5 2.5C12 6 13.5 5 15.5 5 19 5 21 8.5 20.5 12c-2 4.3-8.5 9-8.5 9z"/></svg> Matches</a>
<a class="jk-side-link {{ request()->is('chat*') ? 'active' : '' }}" href="/chat"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/></svg> Chat @if($unreadChats > 0)<span class="jk-dot" style="position:static">{{ $unreadChats > 9 ? '9+' : $unreadChats }}</span>@endif</a>
<a class="jk-side-link {{ request()->is('likes*') ? 'active' : '' }}" href="/likes"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-7.5-4.7-9.5-9C1 8.5 3 5 6.5 5c2 0 3.5 1 4.5 2.5C12 6 13.5 5 15.5 5 19 5 21 8.5 20.5 12c-2 4.3-8.5 9-8.5 9z"/></svg> Likes</a>
<a class="jk-side-link {{ request()->is('visitors*') ? 'active' : '' }}" href="/visitors"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg> Visitors</a>
<a class="jk-side-link {{ request()->is('events*') ? 'active' : '' }}" href="/events"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg> Events</a>
<a class="jk-side-link {{ request()->is('biro-jodoh*') ? 'active' : '' }}" href="/biro-jodoh/taaruf"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7.5-4.7-9.5-9C1 8.5 3 5 6.5 5c2 0 3.5 1 4.5 2.5C12 6 13.5 5 15.5 5 19 5 21 8.5 20.5 12c-2 4.3-8.5 9-8.5 9z"/></svg> Taaruf</a>
<a class="jk-side-link {{ request()->is('forums*') || request()->is('blog*') ? 'active' : '' }}" href="/forums"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/><path d="M8 10h8M8 14h5"/></svg> Komunitas</a>
<a class="jk-side-link {{ request()->is('notifications*') ? 'active' : '' }}" href="/notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></svg> Notifikasi @if($unreadNotifs > 0)<span class="jk-dot" style="position:static">{{ $unreadNotifs > 9 ? '9+' : $unreadNotifs }}</span>@endif</a>
<a class="jk-side-link {{ request()->is('safety*') ? 'active' : '' }}" href="/safety"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Keamanan</a>
<a class="jk-side-link {{ request()->is('premium*') ? 'active' : '' }}" href="/premium"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/></svg> Premium</a>
<a class="jk-side-link {{ request()->is('settings*') ? 'active' : '' }}" href="/settings"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h0a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h0a1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v0a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg> Settings</a>
</nav>
<div style="margin-top:16px;padding:12px">@livewire('notification-bell')</div>
</aside>
<div class="jk-main">
<div class="jk-topbar">
<a href="/home" class="jk-logo" style="text-decoration:none;color:#18181b">Jodoh<span>ku</span></a>
<form action="/discover" method="GET" class="jk-search" role="search" aria-label="Cari member">
<input type="search" name="keyword" placeholder="Cari nama, kota, minat…" aria-label="Cari member" value="{{ request('keyword') }}">
</form>
<div style="margin-left:auto;display:flex;gap:8px;align-items:center">
@auth
<button class="jk-iconbtn" type="button" aria-label="Ganti mode gelap/terang" onclick="try{var h=document.documentElement;var n=h.getAttribute('data-theme')==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('jk-theme',n);}catch(e){}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
<a href="/notifications" class="jk-iconbtn" aria-label="Notifikasi{{ $unreadNotifs > 0 ? ', '.$unreadNotifs.' belum dibaca' : '' }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></svg>@if($unreadNotifs > 0)<span class="jk-dot">{{ $unreadNotifs > 9 ? '9+' : $unreadNotifs }}</span>@endif</a>
<span class="jk-pill {{ auth()->user()->is_premium ? 'premium' : '' }}">{{ auth()->user()->is_premium ? 'PREMIUM' : (auth()->user()->creditBalance() . ' kredit') }}</span>
<a href="/profile/{{ auth()->id() }}" class="jk-avatar sm" aria-label="Profil saya">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ strtoupper(substr((string)(auth()->user()->displayName() ?? '?'),0,1)) }}@endif</a>
@else
<a href="{{ route('login') }}" class="jk-btn jk-btn-like" style="text-decoration:none;flex:none;padding:8px 16px">Masuk</a>
@endauth
</div>
</div>
<div class="jk-layout"><main id="jk-content" class="jk-container">@yield('content')</main>@hasSection('sidebar')<aside class="jk-rightbar" aria-label="Rekomendasi">@yield('sidebar')</aside>@endif</div>
</div>
</div>
<nav class="jk-bottomnav" aria-label="Navigasi utama">
<a class="jk-navlink {{ request()->is('home') ? 'active' : '' }}" href="/home" @if(request()->is('home')) aria-current="page" @endif><svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z"/></svg>Home</a>
<a class="jk-navlink {{ request()->is('discover*') ? 'active' : '' }}" href="/discover" @if(request()->is('discover*')) aria-current="page" @endif><svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>Discover</a>
<a class="jk-navlink {{ request()->is('likes*') ? 'active' : '' }}" href="/likes" @if(request()->is('likes*')) aria-current="page" @endif><svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7.5-4.7-9.5-9C1 8.5 3 5 6.5 5c2 0 3.5 1 4.5 2.5C12 6 13.5 5 15.5 5 19 5 21 8.5 20.5 12c-2 4.3-8.5 9-8.5 9z"/></svg>Likes</a>
<a class="jk-navlink {{ request()->is('chat*') ? 'active' : '' }}" href="/chat" @if(request()->is('chat*')) aria-current="page" @endif><svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/></svg>Chat @if($unreadChats > 0)<span class="jk-dot">{{ $unreadChats > 9 ? '9+' : $unreadChats }}</span>@endif</a>
<a class="jk-navlink {{ request()->is('settings*') || request()->is('profile*') ? 'active' : '' }}" href="/settings" @if(request()->is('settings*') || request()->is('profile*')) aria-current="page" @endif><svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>Profile</a>
</nav>
@livewireScripts
<div x-data="{ show:@json(session('status') ? true : false) }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="jk-toast" style="display:none" x-transition>{{ session('status') }}</div>
<div class="jk-modal-bg" x-show="confirm.open" style="display:none" @click.self="confirm.open = false">
<div class="jk-modal">
<div class="jk-h2" x-text="confirm.title || 'Yakin?'"></div>
<p class="jk-muted" x-text="confirm.text || 'Tindakan ini tidak bisa dibatalkan.'"></p>
<div style="display:flex;gap:8px;margin-top:14px">
<button class="jk-btn jk-btn-pass" @click="confirm.open = false">Batal</button>
<button class="jk-btn jk-btn-like" @click="(typeof confirm.action === 'function') ? confirm.action() : (confirm.action && eval(confirm.action)); confirm.open = false">Ya, lanjut</button>
</div>
</div>
</div>
<script>
window.addEventListener('echo:message-received', e => { if (window.Livewire) { window.Livewire.dispatch('echo-message', e.detail); } });
window.jkConfirm = function (title, text, action) {
    const root = document.querySelector('.jk-body');
    if (root && window.Alpine) { const d = window.Alpine.$data(root); if (d && d.confirm) { d.confirm = { open:true, title, text, action }; return; } }
    if (window.confirm(text || title)) { if (action) { eval(action); } }
};
window.jkToast = function (msg, ok = true) {
    const t = document.createElement('div');
    t.className = 'jk-toast' + (ok ? ' ok' : ' err'); t.textContent = msg;
    document.body.appendChild(t); setTimeout(() => t.remove(), 3500);
};
</script>
@stack('scripts')
</body>
</html>
