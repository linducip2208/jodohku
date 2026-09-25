<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\LikeService;
use Livewire\Component;

class LikeButtons extends Component
{
    public int $userId;

    public string $status = '';

    public ?int $matchedUserId = null;

    public string $matchedName = '';

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    public function like(LikeService $likes): void
    {
        $me = auth()->user();
        if (! $me) {
            $this->status = 'Masuk dulu untuk like.';

            return;
        }
        $target = User::find($this->userId);
        if (! $target) {
            return;
        }
        try {
            $res = $likes->like($me, $target, false);
            if (! empty($res['is_new_match']) && ! empty($res['match'])) {
                $match = $res['match'];
                $partnerId = (int) $match->user_a_id === (int) $me->id ? (int) $match->user_b_id : (int) $match->user_a_id;
                $this->matchedUserId = $partnerId;
                $this->matchedName = (string) ($target->displayName() ?? 'Member');
                $this->status = '';
            } else {
                $this->matchedUserId = null;
                $this->status = 'Like terkirim ❤️';
            }
            $this->dispatch('like-sent', userId: $this->userId);
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function pass(LikeService $likes): void
    {
        $me = auth()->user();
        if (! $me) {
            $this->status = 'Masuk dulu.';

            return;
        }
        $target = User::find($this->userId);
        if (! $target) {
            return;
        }
        try {
            $likes->pass($me, $target);
            $this->status = 'Dilewati.';
            $this->dispatch('passed', userId: $this->userId);
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function superlike(LikeService $likes): void
    {
        $me = auth()->user();
        if (! $me) {
            $this->status = 'Masuk dulu.';

            return;
        }
        $target = User::find($this->userId);
        if (! $target) {
            return;
        }
        try {
            $res = $likes->superLike($me, $target);
            if (! empty($res['is_new_match']) && ! empty($res['match'])) {
                $match = $res['match'];
                $partnerId = (int) $match->user_a_id === (int) $me->id ? (int) $match->user_b_id : (int) $match->user_a_id;
                $this->matchedUserId = $partnerId;
                $this->matchedName = (string) ($target->displayName() ?? 'Member');
                $this->status = '';
            } else {
                $this->matchedUserId = null;
                $this->status = 'Superlike terkirim ✦';
            }
            $this->dispatch('like-sent', userId: $this->userId);
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function favorite(LikeService $likes): void
    {
        $me = auth()->user();
        if (! $me) {
            $this->status = 'Masuk dulu.';

            return;
        }
        $target = User::find($this->userId);
        if (! $target) {
            return;
        }
        try {
            $likes->favorite($me, $target);
            $this->status = 'Ditambah ke favorit ⭐';
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.like-buttons');
    }
}
