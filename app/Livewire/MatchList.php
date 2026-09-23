<?php

namespace App\Livewire;

use App\Models\MatchNote;
use App\Models\UserMatch;
use Livewire\Component;

class MatchList extends Component
{
    /** Draft note bodies keyed by match id (private to the viewer). */
    public array $noteBodies = [];

    public function saveNote(int $matchId): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $match = UserMatch::where('id', $matchId)->where('is_active', true)
            ->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->firstOrFail();
        $body = trim((string) ($this->noteBodies[$matchId] ?? ''));
        $this->validate(['noteBodies.'.$matchId => ['nullable', 'string', 'max:2000']]);
        if ($body === '') {
            MatchNote::where('user_id', $user->id)->where('match_id', $match->id)->delete();
        } else {
            MatchNote::updateOrCreate(
                ['user_id' => $user->id, 'match_id' => $match->id],
                ['body' => $body]
            );
        }
        session()->flash('status', 'Catatan disimpan (privat).');
    }

    public function render()
    {
        $user = auth()->user();
        $matches = collect();
        $notes = collect();
        if ($user) {
            try {
                $matches = UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
                    ->where('is_active', true)->with(['userA.profile', 'userB.profile'])->latest('matched_at')->take(30)->get();
                $notes = MatchNote::where('user_id', $user->id)
                    ->whereIn('match_id', $matches->pluck('id'))->pluck('body', 'match_id');
                foreach ($notes as $mid => $body) {
                    $this->noteBodies[$mid] ??= $body;
                }
            } catch (\Throwable) {
            }
        }

        return view('livewire.match-list', ['matches' => $matches, 'notes' => $notes]);
    }
}
