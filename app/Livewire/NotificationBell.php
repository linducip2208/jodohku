<?php

namespace App\Livewire;

use App\Services\NotificationService;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markAll(NotificationService $svc): void
    {
        $u = auth()->user();
        if ($u) {
            try {
                $svc->markAllRead($u);
            } catch (\Throwable) {
            }
        }
    }

    public function render(NotificationService $svc)
    {
        $count = 0;
        $items = collect();
        $u = auth()->user();
        if ($u) {
            try {
                $count = $svc->unreadCount($u);
                $items = $u->notifications()->take(6)->get();
            } catch (\Throwable) {
            }
        }

        return view('livewire.notification-bell', ['count' => $count, 'items' => $items]);
    }
}
