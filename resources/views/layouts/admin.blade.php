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
<li class="nav-item"><a class="nav-link{{ request()->is('admin') ? ' active' : '' }}" href="/admin"><span class="nav-link-icon"><i class="ti ti-dashboard"></i></span> Dashboard</a></li>
@can('moderator')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/users*') ? ' active' : '' }}" href="/admin/users"><span class="nav-link-icon"><i class="ti ti-users"></i></span> Users</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/profiles*') ? ' active' : '' }}" href="/admin/profiles"><span class="nav-link-icon"><i class="ti ti-id"></i></span> Profiles</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/photos*') ? ' active' : '' }}" href="/admin/photos"><span class="nav-link-icon"><i class="ti ti-photo"></i></span> Photos</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/verification*') ? ' active' : '' }}" href="/admin/verification"><span class="nav-link-icon"><i class="ti ti-badge-check"></i></span> Verification</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/reports*') ? ' active' : '' }}" href="/admin/reports"><span class="nav-link-icon"><i class="ti ti-flag"></i></span> Reports</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/blocks*') ? ' active' : '' }}" href="/admin/blocks"><span class="nav-link-icon"><i class="ti ti-ban"></i></span> Blocks</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/moderation*') ? ' active' : '' }}" href="/admin/moderation"><span class="nav-link-icon"><i class="ti ti-shield-check"></i></span> Moderation</a></li><li class="nav-item"><a class="nav-link{{ request()->is('admin/moderation/words*') ? ' active' : '' }}" href="/admin/moderation/words"><span class="nav-link-icon"><i class="ti ti-book"></i></span> Dictionary</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/fraud*') ? ' active' : '' }}" href="/admin/fraud"><span class="nav-link-icon"><i class="ti ti-alert-triangle"></i></span> Fraud</a></li>
@endcan
@can('admin')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/matching/weights*') ? ' active' : '' }}" href="/admin/matching/weights"><span class="nav-link-icon"><i class="ti ti-heart"></i></span> Matching Weights</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/matching/questions*') ? ' active' : '' }}" href="/admin/matching/questions"><span class="nav-link-icon"><i class="ti ti-question-mark"></i></span> Questions</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/matching/categories*') ? ' active' : '' }}" href="/admin/matching/categories"><span class="nav-link-icon"><i class="ti ti-folder"></i></span> Categories</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/matching/options*') ? ' active' : '' }}" href="/admin/matching/options"><span class="nav-link-icon"><i class="ti ti-list-checks"></i></span> Options</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/matching/versions*') ? ' active' : '' }}" href="/admin/matching/versions"><span class="nav-link-icon"><i class="ti ti-versions"></i></span> Versions</a></li>
@endcan
@can('moderator')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/conversations*') ? ' active' : '' }}" href="/admin/chat/conversations"><span class="nav-link-icon"><i class="ti ti-message-chatbot"></i></span> Conversations</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/messages*') ? ' active' : '' }}" href="/admin/chat/messages"><span class="nav-link-icon"><i class="ti ti-mail"></i></span> Messages</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/requests*') ? ' active' : '' }}" href="/admin/chat/requests"><span class="nav-link-icon"><i class="ti ti-inbox"></i></span> Requests</a></li>
@endcan
@can('admin')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/settings*') ? ' active' : '' }}" href="/admin/chat/settings"><span class="nav-link-icon"><i class="ti ti-message-cog"></i></span> Chat Settings</a></li>
@endcan
@can('operator')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/operators*') ? ' active' : '' }}" href="/admin/chat/operators"><span class="nav-link-icon"><i class="ti ti-headset"></i></span> Operators</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/virtual*') ? ' active' : '' }}" href="/admin/chat/virtual"><span class="nav-link-icon"><i class="ti ti-robot"></i></span> Virtual</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/chat/ai*') ? ' active' : '' }}" href="/admin/chat/ai"><span class="nav-link-icon"><i class="ti ti-sparkles"></i></span> AI</a></li>
@endcan
@can('admin')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/plans*') ? ' active' : '' }}" href="/admin/membership/plans"><span class="nav-link-icon"><i class="ti ti-star"></i></span> Plans</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/subscriptions*') ? ' active' : '' }}" href="/admin/membership/subscriptions"><span class="nav-link-icon"><i class="ti ti-repeat"></i></span> Subscriptions</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/credits*') ? ' active' : '' }}" href="/admin/membership/credits"><span class="nav-link-icon"><i class="ti ti-coins"></i></span> Credits</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/products*') ? ' active' : '' }}" href="/admin/membership/products"><span class="nav-link-icon"><i class="ti ti-package"></i></span> Products</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/payments*') ? ' active' : '' }}" href="/admin/membership/payments"><span class="nav-link-icon"><i class="ti ti-credit-card"></i></span> Payments</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/gateways*') ? ' active' : '' }}" href="/admin/membership/gateways"><span class="nav-link-icon"><i class="ti ti-building-bank"></i></span> Gateways</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/membership/coupons*') ? ' active' : '' }}" href="/admin/membership/coupons"><span class="nav-link-icon"><i class="ti ti-ticket"></i></span> Coupons</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/gifts*') ? ' active' : '' }}" href="/admin/gifts"><span class="nav-link-icon"><i class="ti ti-gift"></i></span> Gifts</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/boosts*') ? ' active' : '' }}" href="/admin/boosts"><span class="nav-link-icon"><i class="ti ti-rocket"></i></span> Boosts</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/ads*') ? ' active' : '' }}" href="/admin/ads"><span class="nav-link-icon"><i class="ti ti-speakerphone"></i></span> Ads</a></li>
@can('moderator')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/community/groups*') ? ' active' : '' }}" href="/admin/community/groups"><span class="nav-link-icon"><i class="ti ti-users-group"></i></span> Groups</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/community/posts*') ? ' active' : '' }}" href="/admin/community/posts"><span class="nav-link-icon"><i class="ti ti-article"></i></span> Posts</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/community/comments*') ? ' active' : '' }}" href="/admin/community/comments"><span class="nav-link-icon"><i class="ti ti-message-circle"></i></span> Comments</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/community/events*') ? ' active' : '' }}" href="/admin/community/events"><span class="nav-link-icon"><i class="ti ti-calendar-event"></i></span> Events</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/community/forums*') ? ' active' : '' }}" href="/admin/community/forums"><span class="nav-link-icon"><i class="ti ti-messages"></i></span> Forums</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/community/blogs*') ? ' active' : '' }}" href="/admin/community/blogs"><span class="nav-link-icon"><i class="ti ti-news"></i></span> Blogs</a></li>
@endcan
<li class="nav-item"><a class="nav-link{{ request()->is('admin/notifications*') ? ' active' : '' }}" href="/admin/notifications"><span class="nav-link-icon"><i class="ti ti-bell"></i></span> Notifications</a></li>
@can('moderator')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/inbox*') ? ' active' : '' }}" href="/admin/inbox"><span class="nav-link-icon"><i class="ti ti-mail-opened"></i></span> Inbox</a></li>
@endcan
<li class="nav-item"><a class="nav-link{{ request()->is('admin/email*') ? ' active' : '' }}" href="/admin/email"><span class="nav-link-icon"><i class="ti ti-send"></i></span> Email</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/analytics*') ? ' active' : '' }}" href="/admin/analytics"><span class="nav-link-icon"><i class="ti ti-chart-line"></i></span> Analytics</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/ai-usage*') ? ' active' : '' }}" href="/admin/ai-usage"><span class="nav-link-icon"><i class="ti ti-brain"></i></span> AI Usage</a></li>
<li class="nav-item"><a class="nav-link{{ request()->is('admin/settings*') ? ' active' : '' }}" href="/admin/settings"><span class="nav-link-icon"><i class="ti ti-settings"></i></span> Settings</a></li>
@can('superadmin')
<li class="nav-item"><a class="nav-link{{ request()->is('admin/feature-flags*') ? ' active' : '' }}" href="/admin/feature-flags"><span class="nav-link-icon"><i class="ti ti-toggle-right"></i></span> Feature Flags</a></li>
@endcan
<li class="nav-item"><a class="nav-link{{ request()->is('admin/audit-logs*') ? ' active' : '' }}" href="/admin/audit-logs"><span class="nav-link-icon"><i class="ti ti-list-details"></i></span> Audit Logs</a></li>
@endcan
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
