<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Admin Jodohku')</title>
<link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/css/tabler.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
@livewireStyles
</head>
<body>
<div class="page">
<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="light">
<div class="container-fluid">
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav"><span class="navbar-toggler-icon"></span></button>
<h1 class="navbar-brand navbar-brand-autodark"><a href="/admin">Jodohku Admin</a></h1>
<div class="collapse navbar-collapse" id="adminNav"><ul class="navbar-nav pt-lg-3">
<li class="nav-item"><a class="nav-link" href="/admin"><span class="nav-link-icon">📊</span> Dashboard</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/users"><span class="nav-link-icon">👥</span> Users</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/profiles"><span class="nav-link-icon">📝</span> Profiles</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/photos"><span class="nav-link-icon">📷</span> Photos</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/verification"><span class="nav-link-icon">✅</span> Verification</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/reports"><span class="nav-link-icon">🚩</span> Reports</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/blocks"><span class="nav-link-icon">⛔</span> Blocks</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/moderation"><span class="nav-link-icon">🛡️</span> Moderation</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/fraud"><span class="nav-link-icon">⚠️</span> Fraud</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/matching/weights"><span class="nav-link-icon">💘</span> Matching Weights</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/matching/questions"><span class="nav-link-icon">❓</span> Questions</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/matching/categories"><span class="nav-link-icon">📁</span> Categories</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/matching/options"><span class="nav-link-icon">🔘</span> Options</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/matching/versions"><span class="nav-link-icon">🔖</span> Versions</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/chat/conversations"><span class="nav-link-icon">💬</span> Conversations</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/chat/messages"><span class="nav-link-icon">✉️</span> Messages</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/chat/requests"><span class="nav-link-icon">📨</span> Requests</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/chat/operators"><span class="nav-link-icon">🎧</span> Operators</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/chat/virtual"><span class="nav-link-icon">🤖</span> Virtual</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/chat/ai"><span class="nav-link-icon">✨</span> AI</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/plans"><span class="nav-link-icon">⭐</span> Plans</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/subscriptions"><span class="nav-link-icon">🔁</span> Subscriptions</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/credits"><span class="nav-link-icon">🪙</span> Credits</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/products"><span class="nav-link-icon">📦</span> Products</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/payments"><span class="nav-link-icon">💳</span> Payments</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/gateways"><span class="nav-link-icon">🏦</span> Gateways</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/membership/coupons"><span class="nav-link-icon">🎟️</span> Coupons</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/gifts"><span class="nav-link-icon">🎁</span> Gifts</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/boosts"><span class="nav-link-icon">🚀</span> Boosts</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/ads"><span class="nav-link-icon">📢</span> Ads</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/community/groups"><span class="nav-link-icon">👪</span> Groups</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/community/posts"><span class="nav-link-icon">📝</span> Posts</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/community/comments"><span class="nav-link-icon">💭</span> Comments</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/community/events"><span class="nav-link-icon">🎉</span> Events</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/community/forums"><span class="nav-link-icon">💬</span> Forums</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/community/blogs"><span class="nav-link-icon">📰</span> Blogs</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/notifications"><span class="nav-link-icon">🔔</span> Notifications</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/email"><span class="nav-link-icon">📧</span> Email</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/analytics"><span class="nav-link-icon">📈</span> Analytics</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/ai-usage"><span class="nav-link-icon">🧠</span> AI Usage</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/settings"><span class="nav-link-icon">⚙️</span> Settings</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/feature-flags"><span class="nav-link-icon">🚦</span> Feature Flags</a></li>
<li class="nav-item"><a class="nav-link" href="/admin/audit-logs"><span class="nav-link-icon">📜</span> Audit Logs</a></li>
</ul></div>
</div></aside>
<div class="page-wrapper">
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">@yield('title', 'Dashboard')</h2></div><div class="col-auto"><a href="/" class="btn btn-ghost-secondary">Lihat Situs</a></div></div></div></div>
<div class="page-body"><div class="container-xl">@yield('content')</div></div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.3.2/dist/js/tabler.min.js"></script>
@livewireScripts
@stack('scripts')
</body>
</html>
