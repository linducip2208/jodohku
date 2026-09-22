<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Services\MatchingEngine;
use Livewire\Component;

class AdminMatchingWeights extends Component
{
    public array $weights = [];

    public string $saved = '';

    public function mount(MatchingEngine $engine): void
    {
        $this->weights = $engine->weights();
    }

    public function save(): void
    {
        $total = max(1, array_sum(array_map('floatval', $this->weights)));
        foreach ($this->weights as $k => $v) {
            $this->weights[$k] = round(((float) $v) / $total * 100, 2);
        }
        try {
            Setting::updateOrCreate(['key' => 'matchmaking.weights'], ['value' => json_encode($this->weights)]);
            $this->saved = 'Bobot tersimpan (normalisasi 100). Berlaku via config matchmaking.';
        } catch (\Throwable $e) {
            $this->saved = 'Gagal menyimpan: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin-matching-weights');
    }
}
