<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $items = AuditLog::with('actor')->latest('id')->paginate(50);

        return $request->wantsJson() ? response()->json($items) : view('admin.audit', ['items' => $items]);
    }
}
