<?php

namespace App\Livewire;

use App\Models\ModerationQueue;
use App\Services\AuditService;
use Livewire\Component;

class AdminModerationQueue extends Component
{
    public string $status = '';

    public function approve(int $id, AuditService $audit): void
    {
        $item = ModerationQueue::find($id);
        if (! $item) {
            return;
        }
        try {
            $item->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewer_id' => auth()->id()]);
            $audit->log('moderation.approve', auth()->user(), $item);
            $this->status = "Item #$id disetujui.";
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function reject(int $id, AuditService $audit): void
    {
        $item = ModerationQueue::find($id);
        if (! $item) {
            return;
        }
        try {
            $item->update(['status' => 'rejected', 'reviewed_at' => now(), 'reviewer_id' => auth()->id()]);
            $audit->log('moderation.reject', auth()->user(), $item);
            $this->status = "Item #$id ditolak.";
        } catch (\Throwable $e) {
            $this->status = $e->getMessage();
        }
    }

    public function render()
    {
        $items = collect();
        try {
            $items = ModerationQueue::latest('id')->take(30)->get();
        } catch (\Throwable) {
        }

        return view('livewire.admin-moderation-queue', ['items' => $items]);
    }
}
