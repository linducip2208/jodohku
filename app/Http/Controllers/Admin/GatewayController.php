<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class GatewayController extends Controller
{
    public function index()
    {
        return response()->json(PaymentGateway::with('settings')->orderBy('sort_order')->get());
    }

    public function toggle(Request $request, PaymentGateway $gateway, AuditService $audit)
    {
        $gateway->update(['is_active' => ! $gateway->is_active]);
        $audit->log('admin.gateway.toggled', $request->user(), $gateway, [], ['is_active' => $gateway->is_active]);

        return response()->json($gateway->fresh());
    }

    public function credentials(Request $request, PaymentGateway $gateway, AuditService $audit)
    {
        $request->validate(['settings' => ['required', 'array'], 'environment' => ['nullable', 'string', 'in:sandbox,production']]);
        $env = $request->input('environment', 'production');
        foreach ($request->input('settings', []) as $key => $value) {
            $gateway->settings()->updateOrCreate(
                ['key' => $key, 'environment' => $env],
                ['value' => Crypt::encryptString((string) $value)]
            );
        }
        $audit->log('admin.gateway.credentials', $request->user(), $gateway);

        return response()->json(['message' => 'Credentials saved.']);
    }

    public function test(Request $request, PaymentGateway $gateway)
    {
        $manager = app(PaymentGatewayManager::class);

        try {
            $driver = $manager->driver($gateway->code);

            return response()->json(['ok' => true, 'driver' => get_class($driver)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function priority(Request $request, PaymentGateway $gateway, AuditService $audit)
    {
        $request->validate(['sort_order' => ['required', 'integer', 'min:0', 'max:1000']]);
        $gateway->update(['sort_order' => (int) $request->input('sort_order')]);
        $audit->log('admin.gateway.priority', $request->user(), $gateway);

        return response()->json($gateway->fresh());
    }
}
