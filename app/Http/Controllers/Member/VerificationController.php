<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationSubmitRequest;
use App\Services\VerificationService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->verificationRequests()->with('documents')->latest('id')->paginate(20);

        return $request->wantsJson()
            ? response()->json($items)
            : view('member.verification', ['requests' => $items]);
    }

    public function store(VerificationSubmitRequest $request, VerificationService $verification)
    {
        $documents = (array) $request->input('documents', []);
        foreach ($request->file('files', []) as $file) {
            $documents[] = [
                'document_type' => $request->input('type'),
                'file_path' => $file->store('verifications', 'private'),
                'mime_type' => $file->getMimeType(),
            ];
        }
        $req = $verification->submit($request->user(), $request->string('type'), $documents, $request->input('notes'));

        return response()->json($req->load('documents'), 201);
    }
}
