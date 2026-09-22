<?php

namespace App\Services;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Counselor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultationService
{
    public function __construct(protected AuditService $audit) {}

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

        return DB::transaction(function () use ($user, $counselor, $data, $at, $duration) {
            $clash = Consultation::where('counselor_id', $counselor->id)
                ->whereNotIn('status', [ConsultationStatus::Cancelled->value, ConsultationStatus::Completed->value])
                ->where('scheduled_at', '<', $at->copy()->addMinutes($duration))
                ->whereRaw('DATETIME(scheduled_at, \'+\' || duration_minutes || \' minutes\') > ?', [$at->toDateTimeString()])
                ->exists();
            if ($clash) {
                throw new \RuntimeException('Counselor is already booked at that time.');
            }
            $consultation = Consultation::create([
                'counselor_id' => $counselor->id,
                'user_id' => $user->id,
                'topic' => $data['topic'],
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $at,
                'duration_minutes' => $duration,
                'status' => ConsultationStatus::Pending,
            ]);
            $this->audit->log('consultation.booked', $user, $consultation, [], ['counselor_id' => $counselor->id]);

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
}
