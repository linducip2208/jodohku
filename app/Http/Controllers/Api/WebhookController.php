<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Idempotent payment webhook. Webhook truth lives in the gateway driver
     * (HMAC / signature validation); replays of the same event_id are no-ops.
     */
    public function __invoke(Request $request, PaymentService $payments, string $gateway)
    {
        $allowed = ['ipaymu', 'xendit', 'midtrans', 'tripay'];
        if (! in_array($gateway, $allowed, true)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        try {
            $webhook = $payments->handleWebhook($gateway, $request->all(), $request->headers->all());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'ok',
            'event_id' => $webhook->payload['event_id'] ?? null,
            'is_processed' => (bool) $webhook->is_processed,
        ]);
    }
}
