<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\DiscoveryService;
use App\Services\LikeService;
use App\Services\PhotoService;
use Livewire\Component;

/**
 * Tinder-style swipe deck over the existing DiscoveryService pool.
 * Swipe right = like, left = pass. All actions reuse LikeService
 * (quotas, matches, rewind) — no new algorithm.
 */
class SwipeDeck extends Component
{
    /** @var array<int> candidate ids in deck order */
    public array $stack = [];

    /** @var array<int> ids already swiped this session */
    public array $seen = [];

    public array $filters = [];

    public int $photoIndex = 0;

    public string $status = '';

    public ?int $matchedUserId = null;

    public string $matchedName = '';

    public bool $deckEmpty = false;

    public function mount(): void
    {
        $this->filters = array_filter([
            'min_age' => is_numeric(request('min_age')) ? (int) request('min_age') : null,
            'max_age' => is_numeric(request('max_age')) ? (int) request('max_age') : null,
            'gender' => in_array(request('gender'), ['male', 'female'], true) ? request('gender') : null,
            'city' => is_string(request('city')) && request('city') !== '' ? mb_substr(trim(request('city')), 0, 120) : null,
            'sort' => 'compatibility',
        ]);
        $this->refill();
    }

    protected function current(): ?User
    {
        $id = $this->stack[0] ?? null;

        return $id ? User::with(['profile', 'interests'])->find($id) : null;
    }

    protected function refill(): void
    {
        $me = auth()->user();
        if (! $me) {
            return;
        }
        try {
            $discovery = app(DiscoveryService::class);
            $filters = array_merge($this->filters, ['exclude_ids' => $this->seen]);
            $page = $discovery->discover($me, $filters, 20);
            foreach ($page->items() as $cand) {
                if (! in_array($cand->id, $this->seen, true) && ! in_array($cand->id, $this->stack, true)) {
                    $this->stack[] = $cand->id;
                }
            }
        } catch (\Throwable) {
        }
        $this->deckEmpty = empty($this->stack);
    }

    protected function shift(): void
    {
        $id = array_shift($this->stack);
        if ($id) {
            $this->seen[] = $id;
        }
        $this->photoIndex = 0;
        if (count($this->stack) < 5) {
            $this->refill();
        }
        $this->deckEmpty = empty($this->stack);
    }

    public function like(LikeService $likes): void
    {
        $cand = $this->current();
        if (! $cand || ! auth()->user()) {
            return;
        }
        try {
            $res = $likes->like(auth()->user(), $cand, false);
            if (! empty($res['is_new_match'])) {
                $this->matchedUserId = $cand->id;
                $this->matchedName = (string) ($cand->displayName() ?? 'Member');
                $this->status = '';
            }
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
        $this->shift();
    }

    public function pass(LikeService $likes): void
    {
        $cand = $this->current();
        if (! $cand || ! auth()->user()) {
            return;
        }
        try {
            $likes->pass(auth()->user(), $cand);
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
        $this->shift();
    }

    public function superlike(LikeService $likes): void
    {
        $cand = $this->current();
        if (! $cand || ! auth()->user()) {
            return;
        }
        try {
            $res = $likes->superLike(auth()->user(), $cand);
            if (! empty($res['is_new_match'])) {
                $this->matchedUserId = $cand->id;
                $this->matchedName = (string) ($cand->displayName() ?? 'Member');
                $this->status = '';
            } else {
                $this->status = 'Superlike terkirim ✦';
            }
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
        $this->shift();
    }

    public function rewind(LikeService $likes): void
    {
        if (! auth()->user()) {
            return;
        }
        try {
            $res = $likes->rewind(auth()->user());
            if (empty($res)) {
                $this->status = 'Tidak ada aksi untuk diurungkan.';

                return;
            }
            // Put the undone profile back on top.
            $lastSeen = array_pop($this->seen);
            if ($lastSeen && ! in_array($lastSeen, $this->stack, true)) {
                array_unshift($this->stack, $lastSeen);
            }
            $this->status = 'Aksi terakhir diurungkan.';
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
        $this->photoIndex = 0;
        $this->deckEmpty = empty($this->stack);
    }

    public function photoNext(): void
    {
        $this->photoIndex++;
    }

    public function photoPrev(): void
    {
        $this->photoIndex = max(0, $this->photoIndex - 1);
    }

    public function render(DiscoveryService $discovery, PhotoService $photos)
    {
        $me = auth()->user();
        $cand = $this->current();
        if ($this->deckEmpty || ! $cand) {
            return view('livewire.swipe-deck-empty');
        }
        $images = [];
        $distance = null;
        if ($me) {
            try {
                $images = $photos->visibleTo($cand, $me)->values()->all();
            } catch (\Throwable) {
            }
            try {
                $distance = $discovery->distanceKm($me, $cand);
            } catch (\Throwable) {
            }
        }
        if ($images && $this->photoIndex >= count($images)) {
            $this->photoIndex = 0;
        }

        return view('livewire.swipe-deck', [
            'cand' => $cand,
            'images' => $images,
            'distance' => $distance,
        ]);
    }
}
