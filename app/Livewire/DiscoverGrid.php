<?php

namespace App\Livewire;

use App\Services\DiscoveryService;
use Livewire\Component;

class DiscoverGrid extends Component
{
    public int $minAge = 18;

    public int $maxAge = 45;

    public int $maxDistance = 200;

    public string $gender = '';

    public string $city = '';

    public string $education = '';

    public bool $verified = false;

    public bool $online = false;

    public bool $premium = false;

    public string $sort = 'compatibility';

    public string $tab = 'recommended';

    public string $keyword = '';

    public int $limit = 20;

    public function mount(): void
    {
        $this->keyword = (string) request('keyword', '');
        // Landing search deep-links here (guests land after login).
        if (is_string(request('gender')) && in_array(request('gender'), ['male', 'female'], true)) {
            $this->gender = request('gender');
        }
        if (is_string(request('city')) && mb_strlen(request('city')) <= 120) {
            $this->city = trim((string) request('city'));
        }
        if (is_numeric(request('min_age'))) {
            $this->minAge = max(17, min(80, (int) request('min_age')));
        }
        if (is_numeric(request('max_age'))) {
            $this->maxAge = max(17, min(80, (int) request('max_age')));
        }
        if ($this->minAge > $this->maxAge) {
            [$this->minAge, $this->maxAge] = [$this->maxAge, $this->minAge];
        }
    }

    public function updated($field): void
    {
        $this->resetPage();
    }

    public function resetPage(): void
    {
        $this->dispatch('discover-updated');
    }

    public function resetFilters(): void
    {
        $this->reset(['minAge', 'maxAge', 'maxDistance', 'gender', 'city', 'education', 'verified', 'online', 'premium', 'sort', 'tab', 'keyword', 'limit']);
        $this->minAge = 18;
        $this->maxAge = 45;
        $this->maxDistance = 200;
        $this->sort = 'compatibility';
        $this->tab = 'recommended';
        $this->limit = 20;
    }

    public function loadMore(): void
    {
        $this->limit = min(100, $this->limit + 20);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['recommended', 'new', 'active', 'nearby', 'serious'], true) ? $tab : 'recommended';
        // Tabs reuse existing sort/filter logic — no new algorithm.
        match ($this->tab) {
            'new' => $this->sort = 'newest',
            'active' => $this->sort = 'active',
            'nearby' => $this->sort = 'distance',
            default => $this->sort = 'compatibility',
        };
        if ($this->tab === 'active') {
            $this->online = true;
        }
        $this->dispatch('discover-updated');
    }

    public function filters(): array
    {
        $filters = [
            'min_age' => $this->minAge,
            'max_age' => $this->maxAge,
            'max_distance_km' => $this->maxDistance,
            'gender' => $this->gender ?: null,
            'city' => $this->city ?: null,
            'education' => $this->education ?: null,
            'verified' => $this->verified ?: null,
            'online' => $this->online ?: null,
            'premium' => $this->premium ?: null,
            'keyword' => $this->keyword ?: null,
            'sort' => $this->sort,
        ];
        // "Serius" reuses relationship_goal filter already supported by DiscoveryService.
        if ($this->tab === 'serious') {
            $filters['relationship_goal'] = 'marriage';
        }
        if ($this->tab === 'nearby') {
            $filters['max_distance_km'] = min($this->maxDistance, 50);
        }

        return $filters;
    }

    public function render(DiscoveryService $discovery)
    {
        $user = auth()->user();
        $candidates = collect();
        $hasMore = false;
        if ($user) {
            try {
                $paginator = $discovery->discover($user, array_filter($this->filters(), fn ($v) => $v !== null && $v !== ''), $this->limit);
                $candidates = collect($paginator->items());
                $hasMore = $paginator->hasMorePages();
            } catch (\Throwable) {
                $candidates = collect();
            }
        }

        return view('livewire.discover-grid', ['candidates' => $candidates, 'hasMore' => $hasMore]);
    }
}
