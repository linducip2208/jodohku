<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewaySetting;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            ['code' => 'ipaymu', 'name' => 'iPaymu', 'description' => 'QRIS, VA, e-wallet via iPaymu.', 'sort_order' => 10],
            ['code' => 'xendit', 'name' => 'Xendit', 'description' => 'Invoice + e-wallet via Xendit.', 'sort_order' => 20],
            ['code' => 'midtrans', 'name' => 'Midtrans', 'description' => 'Snap + payment link via Midtrans.', 'sort_order' => 30],
            ['code' => 'tripay', 'name' => 'Tripay', 'description' => 'QRIS + convenience store via Tripay.', 'sort_order' => 40],
        ];
        foreach ($gateways as $g) {
            $gw = PaymentGateway::firstOrCreate(['code' => $g['code']], $g + ['is_active' => true]);
            // Placeholder sandbox settings (encrypted at rest when is_secret).
            $defaults = match ($g['code']) {
                'ipaymu' => [['key' => 'va', 'secret' => false], ['key' => 'api_key', 'secret' => true]],
                'xendit' => [['key' => 'api_key', 'secret' => true], ['key' => 'webhook_token', 'secret' => true]],
                'midtrans' => [['key' => 'server_key', 'secret' => true], ['key' => 'client_key', 'secret' => false], ['key' => 'merchant_id', 'secret' => false]],
                'tripay' => [['key' => 'api_key', 'secret' => true], ['key' => 'private_key', 'secret' => true], ['key' => 'merchant_code', 'secret' => false]],
                default => [],
            };
            foreach ($defaults as $d) {
                PaymentGatewaySetting::firstOrCreate(
                    ['payment_gateway_id' => $gw->id, 'environment' => 'sandbox', 'key' => $d['key']],
                    ['value' => '', 'is_secret' => $d['secret']]
                );
            }
        }
    }
}
