<?php

namespace App\Services;

use App\Enums\ConsultationStatus;
use App\Models\CompatibilityReport;
use App\Models\Consultation;
use App\Models\Counselor;
use App\Models\User;
use App\Notifications\ConsultationStatusChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultationService
{
    public function __construct(protected AuditService $audit, protected NotificationService $notifications) {}

    public function counselors(int $perPage = 20)
    {
        return Counselor::active()->with('user')->orderBy('id')->paginate($perPage);
    }

    public function book(User $user, Counselor $counselor, array $data): Consultation
    {
        if (! $counselor->is_active) {
            throw new \RuntimeException('Counselor is not available.');
        }
        $at = Carbon::parse($data['scheduled_at']);
        if ($at->isPast()) {
            throw new \InvalidArgumentException('Schedule must be in the future.');
        }
        $duration = max(15, min(180, (int) ($data['duration_minutes'] ?? 30)));

        $sharedReportId = null;
        if (! empty($data['share_report'])) {
            $sharedReportId = (int) ($data['shared_report_id'] ?? 0);
            $owns = $sharedReportId > 0 && CompatibilityReport::where('id', $sharedReportId)->where('user_id', $user->id)->exists();
            if (! $owns) {
                throw new \InvalidArgumentException('Shared report must be one of your own compatibility reports.');
            }
        }

        return DB::transaction(function () use ($user, $counselor, $data, $at, $duration, $sharedReportId) {
            $newEnd = $at->copy()->addMinutes($duration);
            // Portable overlap check (works on SQLite/MySQL/PgSQL — the old
            // DATETIME('+...' || ...) raw only ran on SQLite). Narrow in SQL,
            // decide exactly in PHP, rows locked to serialize concurrent books.
            $neighbors = Consultation::where('counselor_id', $counselor->id)
                ->whereNotIn('status', [ConsultationStatus::Cancelled->value, ConsultationStatus::Completed->value])
                ->where('scheduled_at', '<', $newEnd)
                ->where('scheduled_at', '>', $at->copy()->subMinutes(180))
                ->lockForUpdate()->get();
            foreach ($neighbors as $existing) {
                $existEnd = Carbon::parse($existing->scheduled_at)->addMinutes((int) ($existing->duration_minutes ?: 30));
                $existStart = Carbon::parse($existing->scheduled_at);
                if ($existStart->lt($newEnd) && $existEnd->gt($at)) {
                    throw new \RuntimeException('Counselor is already booked at that time.');
                }
            }
            $consultation = Consultation::create([
                'counselor_id' => $counselor->id,
                'user_id' => $user->id,
                'topic' => $data['topic'],
                'notes' => $data['notes'] ?? null,
                'share_report' => $sharedReportId !== null,
                'shared_report_id' => $sharedReportId,
                'scheduled_at' => $at,
                'duration_minutes' => $duration,
                'status' => ConsultationStatus::Pending,
            ]);
            $this->audit->log('consultation.booked', $user, $consultation, [], ['counselor_id' => $counselor->id]);
            if ($counselor->user) {
                $this->notifications->send($counselor->user, new ConsultationStatusChanged($consultation->fresh()));
            }

            return $consultation->fresh();
        });
    }

    public function confirm(Consultation $consultation, User $actor): Consultation
    {
        return $this->transition($consultation, $actor, ConsultationStatus::Confirmed, [ConsultationStatus::Pending]);
    }

    public function complete(Consultation $consultation, User $actor): Consultation
    {
        return $this->transition($consultation, $actor, ConsultationStatus::Completed, [ConsultationStatus::Confirmed]);
    }

    public function cancel(Consultation $consultation, User $actor): Consultation
    {
        return $this->transition($consultation, $actor, ConsultationStatus::Cancelled, [ConsultationStatus::Pending, ConsultationStatus::Confirmed]);
    }

    protected function transition(Consultation $consultation, User $actor, ConsultationStatus $to, array $from): Consultation
    {
        if (! in_array($consultation->status, $from, true)) {
            throw new \RuntimeException('Consultation cannot transition from '.$consultation->status->value.'.');
        }
        $consultation->update(['status' => $to, 'decided_at' => now()]);
        $this->audit->log('consultation.'.$to->value, $actor, $consultation);
        if ($consultation->user && (int) $consultation->user_id !== (int) $actor->id) {
            $this->notifications->send($consultation->user, new ConsultationStatusChanged($consultation->fresh()));
        }

        return $consultation->fresh();
    }

    public function forUser(User $user, int $perPage = 20)
    {
        return Consultation::where('user_id', $user->id)->with('counselor.user')->latest('scheduled_at')->paginate($perPage);
    }

    public function forCounselor(Counselor $counselor, int $perPage = 20)
    {
        return $counselor->consultations()->with('user')->latest('scheduled_at')->paginate($perPage);
    }

    /** Bookings assigned to the counselor account, with explicitly shared reports only. */
    public function forCounselorUser(User $user, int $perPage = 20)
    {
        $counselor = Counselor::where('user_id', $user->id)->firstOrFail();

        return $counselor->consultations()->with(['user', 'sharedReport'])->latest('scheduled_at')->paginate($perPage);
    }
}
