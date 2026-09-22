<?php

namespace App\Livewire;

use App\Models\VirtualConversation;
use App\Services\OperatorService;
use Livewire\Component;

class AdminOperatorPanel extends Component
{
    public string $status = '';

    public function takeover(int $id, OperatorService $ops): void
    {
        $vc = VirtualConversation::find($id);
        $me = auth()->user();
        if (! $vc || ! $me) {
            return;
        }
        try {
            $ops->takeover($vc, $me);
            $this->status = "Takeover VC #$id.";
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function pause(int $id, OperatorService $ops): void
    {
        $vc = VirtualConversation::find($id);
        $me = auth()->user();
        if (! $vc || ! $me) {
            return;
        }
        try {
            $ops->pause($vc, $me);
            $this->status = "Paused VC #$id.";
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function resume(int $id, OperatorService $ops): void
    {
        $vc = VirtualConversation::find($id);
        $me = auth()->user();
        if (! $vc || ! $me) {
            return;
        }
        try {
            $ops->resume($vc, $me);
            $this->status = "Resumed VC #$id.";
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function render()
    {
        $items = collect();
        try {
            $items = VirtualConversation::latest('id')->take(20)->get();
        } catch (\Throwable) {
        }

        return view('livewire.admin-operator-panel', ['items' => $items]);
    }
}
