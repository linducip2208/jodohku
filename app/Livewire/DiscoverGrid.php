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
        $this->reset(['minAge','maxAge','maxDistance','gender','city','education','verified','online','premium','sort']);
        $this->minAge = 18; $this->maxAge = 45; $this->maxDistance = 200; $this->sort = 'compatibility';
    }

    public function filters(): array
    {
        return [
            'min_age' => $this->minAge,
            'max_age' => $this->maxAge,
            'max_distance_km' => $this->maxDistance,
            'gender' => $this->gender ?: null,
            'city' => $this->city ?: null,
            'education' => $this->education ?: null,
            'verified' => $this->verified ?: null,
            'online' => $this->online ?: null,
            'premium' => $this->premium ?: null,
            'sort' => $this->sort,
        ];
    }

    public function render(DiscoveryService $discovery)
    {
        $user = auth()->user();
        $candidates = collect();
        if ($user) {
            try {
                $paginator = $discovery->discover($user, array_filter($this->filters(), fn ($v) => $v !== null && $v !== ''), 20);
                $candidates = collect($paginator->items());
            } catch (\Throwable) {
                $candidates = collect();
            }
        }
        return view('livewire.discover-grid', ['candidates' => $candidates]);
    }
}
