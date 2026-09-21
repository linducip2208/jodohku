<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function overview(Request $request)
    {
        $this->authorize('viewAdminOverview', User::class);

        return response()->json([
            'users_total' => User::count(),
            'reports_pending' => Report::where('status', ReportStatus::Pending->value)->count(),
            'verifications_pending' => \App\Models\VerificationRequest::where('status', \App\Enums\VerificationStatus::Pending->value)->count(),
            'moderation_pending' => \App\Models\ModerationQueue::where('status', 'pending')->count(),
        ]);
    }
}
