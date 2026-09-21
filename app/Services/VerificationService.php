<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Events\ProfileVerified;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\DB;

class VerificationService
{
    public function __construct(protected AuditService $audit) {}

    public function submit(User $user, VerificationType|string $type, array $documents = [], ?string $notes = null): VerificationRequest
    {
        $typeVal = $type instanceof VerificationType ? $type->value : $type;

        return DB::transaction(function () use ($user, $typeVal, $documents, $notes) {
            $existing = VerificationRequest::where('user_id', $user->id)
                ->where('type', $typeVal)
                ->whereIn('status', [VerificationStatus::Pending->value, VerificationStatus::UnderReview->value])
                ->first();
            if ($existing) {
                return $existing; // idempotent
            }
            $req = VerificationRequest::create([
                'user_id' => $user->id,
                'type' => $typeVal,
                'status' => VerificationStatus::Pending,
                'notes' => $notes,
            ]);
            foreach ($documents as $doc) {
                $req->documents()->create([
                    'document_type' => $doc['document_type'] ?? $typeVal,
                    'file_path' => $doc['file_path'],
                    'mime_type' => $doc['mime_type'] ?? null,
                    'extracted_data' => $doc['extracted_data'] ?? null,
                ]);
            }
            $this->audit->log('verification.submitted', $user, $req);

            return $req;
        });
    }

    public function approve(VerificationRequest $request, User $reviewer): bool
    {
        return DB::transaction(function () use ($request, $reviewer) {
            $ok = $request->approve($reviewer);
            if ($ok) {
                $this->audit->log('verification.approved', $reviewer, $request);
                event(new ProfileVerified($request->user, $request->fresh()));
            }

            return $ok;
        });
    }

    public function reject(VerificationRequest $request, User $reviewer, string $notes = ''): bool
    {
        return DB::transaction(function () use ($request, $reviewer, $notes) {
            $ok = $request->reject($reviewer, $notes);
            if ($ok) {
                $this->audit->log('verification.rejected', $reviewer, $request);
            }

            return $ok;
        });
    }
}
