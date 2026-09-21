<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Jodohku')</title>
<meta name="description" content="Aplikasi kencan Jodohku — discover, matches, chat realtime.">
<link rel="icon" href="/favicon.ico">
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
@vite(['resources/css/app.css', 'resources/css/member.css', 'resources/js/app.js'])
@else
<style>body{margin:0;font-family:'Instrument Sans',system-ui,sans-serif;background:#fafafb;color:#18181b}</style>
@endif
<style>body{margin:0;font-family:'Instrument Sans',system-ui,sans-serif;background:#fafafb;color:#18181b}</style>
@livewireStyles
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="jk-body" x-data="{ sidebar:false }">
<div class="jk-app">
<aside class="jk-sidebar" style="display:block" x-show="true">
<div class="jk-logo" style="padding:4px 12px 16px">Jodoh<span style="background:linear-gradient(135deg,#f43f5e,#8b5cf6);-webkit-background-clip:text;background-clip:text;color:transparent">ku</span></div>
<nav>
<a class="jk-side-link {{ request()->is('home') ? 'active' : '' }}" href="/home"><span>🏠</span> Home</a>
<a class="jk-side-link {{ request()->is('discover*') ? 'active' : '' }}" href="/discover"><span>🔍</span> Discover</a>
<a class="jk-side-link {{ request()->is('matches*') ? 'active' : '' }}" href="/matches"><span>💘</span> Matches</a>
<a class="jk-side-link {{ request()->is('chat*') ? 'active' : '' }}" href="/chat"><span>💬</span> Chat</a>
<a class="jk-side-link {{ request()->is('likes*') ? 'active' : '' }}" href="/likes"><span>❤️</span> Likes</a>
<a class="jk-side-link {{ request()->is('visitors*') ? 'active' : '' }}" href="/visitors"><span>👀</span> Visitors</a>
<a class="jk-side-link {{ request()->is('events*') ? 'active' : '' }}" href="/events"><span>🎉</span> Events</a>
<a class="jk-side-link {{ request()->is('premium*') ? 'active' : '' }}" href="/premium"><span>⭐</span> Premium</a>
<a class="jk-side-link {{ request()->is('settings*') ? 'active' : '' }}" href="/settings"><span>⚙️</span> Settings</a>
</nav>
<div style="margin-top:16px;padding:12px">@livewire('notification-bell')</div>
</aside>
<div class="jk-main">
<div class="jk-topbar">
<a href="/home" class="jk-logo" style="text-decoration:none;color:#18181b">Jodoh<span>ku</span></a>
<div style="margin-left:auto;display:flex;gap:8px;align-items:center">
@auth
<span class="jk-pill {{ auth()->user()->is_premium ? 'premium' : '' }}">{{ auth()->user()->is_premium ? 'PREMIUM' : (auth()->user()->creditBalance() . ' kredit') }}</span>
<a href="/profile/{{ auth()->id() }}" style="text-decoration:none">👤</a>
@else
<a href="{{ route('login') }}" class="jk-btn jk-btn-like" style="text-decoration:none;flex:none;padding:8px 16px">Masuk</a>
@endauth
</div>
</div>
<div class="jk-container">@yield('content')</div>
</div>
</div>
<nav class="jk-bottomnav">
<a class="jk-navlink {{ request()->is('home') ? 'active' : '' }}" href="/home"><span class="ico">🏠</span>Home</a>
<a class="jk-navlink {{ request()->is('discover*') ? 'active' : '' }}" href="/discover"><span class="ico">🔍</span>Discover</a>
<a class="jk-navlink {{ request()->is('matches*') ? 'active' : '' }}" href="/matches"><span class="ico">💘</span>Matches</a>
<a class="jk-navlink {{ request()->is('chat*') ? 'active' : '' }}" href="/chat"><span class="ico">💬</span>Chat</a>
<a class="jk-navlink {{ request()->is('settings*') || request()->is('profile*') ? 'active' : '' }}" href="/settings"><span class="ico">👤</span>Profile</a>
</nav>
@livewireScripts
<script>
window.addEventListener('echo:message-received', e => { if (window.Livewire) { window.Livewire.dispatch('echo-message', e.detail); } });
</script>
@stack('scripts')
</body>
</html>
