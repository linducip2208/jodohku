<?php

namespace App\Livewire;

use App\Models\UserMatch;
use Livewire\Component;

class MatchList extends Component
{
    public function render()
    {
        $user = auth()->user();
        $matches = collect();
        if ($user) {
            try {
                $matches = UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
                    ->where('is_active', true)->with(['userA.profile', 'userB.profile'])->latest('matched_at')->take(30)->get();
            } catch (\Throwable) {
            }
        }

        return view('livewire.match-list', ['matches' => $matches]);
    }
}
