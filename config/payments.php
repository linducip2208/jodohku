<?php

use App\Payments\IpaymuGateway;
use App\Payments\MidtransGateway;
use App\Payments\TripayGateway;
use App\Payments\XenditGateway;

return [
    'default' => env('PAYMENT_GATEWAY', 'midtrans'),
    'currency' => env('PAYMENT_CURRENCY', 'IDR'),

    // Webhook truth: every gateway verifies signatures server-side before fulfill().
    // Secrets are read ONLY from env and stored encrypted at rest (Crypt) via Admin\GatewayController.
    'gateways' => [
        'ipaymu' => [
            'driver' => IpaymuGateway::class,
            'enabled' => env('IPAYMU_ENABLED', false),
            'priority' => 10,
            'base_url' => env('IPAYMU_BASE_URL', 'https://sandbox.ipaymu.com/api/v2'),
            'va' => env('IPAYMU_VA', ''),
            'api_key' => env('IPAYMU_API_KEY', ''),
            'webhook_secret' => env('IPAYMU_CALLBACK_SECRET', ''),
            'fee_percent' => 2.0,
        ],
        'xendit' => [
            'driver' => XenditGateway::class,
            'enabled' => env('XENDIT_ENABLED', false),
            'priority' => 20,
            'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
            'api_key' => env('XENDIT_API_KEY', ''),
            'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
            'webhook_secret' => env('XENDIT_WEBHOOK_TOKEN', ''),
            'fee_percent' => 2.5,
        ],
        'midtrans' => [
            'driver' => MidtransGateway::class,
            'enabled' => env('MIDTRANS_ENABLED', false),
            'priority' => 30,
            'base_url' => env('MIDTRANS_BASE_URL', 'https://api.sandbox.midtrans.com'),
            'server_key' => env('MIDTRANS_SERVER_KEY', ''),
            'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
            'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
            // Midtrans webhook authenticity = HMAC sha512(order_id+status_code+gross_amount+server_key).
            'fee_percent' => 2.9,
        ],
        'tripay' => [
            'driver' => TripayGateway::class,
            'enabled' => env('TRIPAY_ENABLED', false),
            'priority' => 40,
            'base_url' => env('TRIPAY_BASE_URL', 'https://tripay.co.id/api-sandbox'),
            'api_key' => env('TRIPAY_API_KEY', ''),
            'private_key' => env('TRIPAY_PRIVATE_KEY', ''),
            'merchant_code' => env('TRIPAY_MERCHANT_CODE', ''),
            'webhook_secret' => env('TRIPAY_CALLBACK_SECRET', ''),
            'fee_percent' => 2.0,
        ],
    ],

    'webhook' => [
        'tolerance_seconds' => 300,
        'store_raw_payload' => true,
    ],
];
