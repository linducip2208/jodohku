<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\ContactBlockService;
use Illuminate\Http\Request;

class ContactBlockController extends Controller
{
    public function show(Request $request, ContactBlockService $contacts)
    {
        $status = $contacts->status($request->user());

        return $request->wantsJson()
            ? response()->json($status)
            : view('member.safety.contacts', ['status' => $status]);
    }

    /**
     * Accepts pasted numbers (one per line). They are hashed immediately;
     * raw numbers are never persisted anywhere.
     */
    public function store(Request $request, ContactBlockService $contacts)
    {
        $data = $request->validate([
            'phones' => ['nullable', 'string', 'max:20000'],
            'contacts' => ['nullable', 'array', 'max:1000'],
            'contacts.*' => ['string', 'max:30'],
        ]);
        $phones = $data['contacts'] ?? preg_split('/[\r\n,;]+/', (string) ($data['phones'] ?? ''));
        $result = $contacts->import($request->user(), array_map('strval', (array) $phones));

        return $request->wantsJson()
            ? response()->json($result)
            : back()->with('status', "Kontak diproses: {$result['imported']} hash, {$result['matched']} akun dicocokkan dan diblokir.");
    }

    public function destroy(Request $request, ContactBlockService $contacts)
    {
        $contacts->removeAll($request->user());

        return $request->wantsJson()
            ? response()->json(['message' => 'Contact blocking disabled.'])
            : back()->with('status', 'Data hash kontak dihapus; blokir kontak dibatalkan.');
    }
}
