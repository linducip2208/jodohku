<?php

namespace App\Livewire;

use App\Models\ProfileView;
use Livewire\Component;

class VisitorList extends Component
{
    public string $tab = 'visitors';

    public function setTab(string $t): void { $this->tab = $t; }

    public function render()
    {
        $user = auth()->user();
        $visitors = collect();
        $likers = collect();
        if ($user) {
            try {
                $visitors = ProfileView::where('viewed_id', $user->id)->with('viewer')->latest('id')->take(30)->get();
            } catch (\Throwable) {}
            try {
                $likers = $user->likesReceived()->with('liker')->latest('id')->take(30)->get();
            } catch (\Throwable) {}
        }
        return view('livewire.visitor-list', ['visitors' => $visitors, 'likers' => $likers]);
    }
}
