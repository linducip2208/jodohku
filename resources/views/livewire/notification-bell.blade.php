<div style="position:relative;display:inline-block" wire:poll.30s x-data x-init="try { if (window.Echo) { window.Echo.private('users.{{ auth()->id() }}').listen('.notification.received', () => { try { $wire.$refresh(); } catch (e) {} }); } } catch (e) {}">
<a href="/notifications" style="text-decoration:none;font-size:22px">🔔@if($count>0)<span style="background:#f43f5e;color:#fff;font-size:11px;font-weight:800;border-radius:999px;padding:1px 7px;position:relative;top:-10px;left:-6px">{{ $count }}</span>@endif</a>
</div>
