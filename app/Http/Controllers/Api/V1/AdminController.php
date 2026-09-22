<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ModerationQueue;
use App\Models\Report;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function overview(Request $request)
    {
        $this->authorize('viewAdminOverview', User::class);

        return response()->json([
            'users_total' => User::count(),
            'reports_pending' => Report::where('status', ReportStatus::Pending->value)->count(),
            'verifications_pending' => VerificationRequest::where('status', VerificationStatus::Pending->value)->count(),
            'moderation_pending' => ModerationQueue::where('status', 'pending')->count(),
        ]);
    }
}
