<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get()->groupBy('group');

        return $request->wantsJson()
            ? response()->json($settings)
            : view('admin.settings', ['settings' => $settings]);
    }

    public function update(Request $request, AuditService $audit)
    {
        $request->validate(['settings' => ['required', 'array', 'max:200']]);
        foreach ($request->input('settings', []) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], [
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
                'group' => explode('.', (string) $key)[0] ?? 'general',
            ]);
        }
        $audit->log('admin.settings.updated', $request->user());

        return response()->json(['message' => 'Settings saved.']);
    }

    public function flags(Request $request, AuditService $audit)
    {
        if ($request->isMethod('post') || $request->isMethod('put')) {
            $request->validate(['flags' => ['required', 'array']]);
            foreach ((array) $request->input('flags') as $flag => $enabled) {
                Setting::updateOrCreate(['key' => 'feature.'.$flag], ['value' => $enabled ? '1' : '0', 'group' => 'features']);
            }
            $audit->log('admin.flags.updated', $request->user());

            return response()->json(['flags' => $request->input('flags')]);
        }

        return response()->json(config('jodohku.features'));
    }
}
