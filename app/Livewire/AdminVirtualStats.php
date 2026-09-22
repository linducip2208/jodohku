<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

class AdminVirtualStats extends Component
{
    public function render()
    {
        $stats = ['virtual' => 0, 'ai' => 0, 'real' => 0, 'online' => 0];
        try {
            $stats['virtual'] = User::where('account_type', 'virtual')->count();
            $stats['ai'] = User::where('account_type', 'ai')->count();
            $stats['real'] = User::where('account_type', 'real')->count();
            $stats['online'] = User::where('is_online', true)->count();
        } catch (\Throwable) {
        }

        return view('livewire.admin-virtual-stats', ['stats' => $stats]);
    }
}
