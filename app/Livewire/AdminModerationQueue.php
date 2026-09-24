<?php

namespace App\Livewire;

use App\Models\ModerationQueue;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AdminModerationQueue extends Component
{
    public string $status = '';

    /** @var array<int> */
    public array $selected = [];

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

    /** Bulk approve/reject checked items (max 100, transactional). */
    public function bulkApprove(AuditService $audit): void
    {
        $this->bulkDecide('approved', $audit);
    }

    public function bulkReject(AuditService $audit): void
    {
        $this->bulkDecide('rejected', $audit);
    }

    protected function bulkDecide(string $action, AuditService $audit): void
    {
        $ids = array_values(array_unique(array_map('intval', array_slice($this->selected, 0, 100))));
        if (! $ids) {
            $this->status = 'Pilih minimal satu item.';

            return;
        }
        $done = 0;
        try {
            DB::transaction(function () use ($ids, $action, $audit, &$done) {
                foreach (ModerationQueue::whereIn('id', $ids)->get() as $item) {
                    $item->update(['status' => $action, 'reviewed_at' => now(), 'reviewer_id' => auth()->id()]);
                    $audit->log('moderation.'.$action, auth()->user(), $item);
                    $done++;
                }
            });
            $this->status = $done === 1 ? "1 item diproses ($action)." : "$done item diproses ($action).";
            $this->selected = [];
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
